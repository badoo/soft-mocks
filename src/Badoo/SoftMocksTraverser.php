<?php

namespace Badoo;

class SoftMocksTraverser extends \PhpParser\NodeVisitorAbstract
{
    private static $ignore_functions = [
        "get_called_class" => true,
        "get_parent_class" => true,
        "get_class" => true,
        "extract" => true,
        "func_get_args" => true,
        "func_get_arg" => true,
        "func_num_args" => true,
        "parse_str" => true,
        "usort" => true,
        "uasort" => true,
        "uksort" => true,
        "array_walk_recursive" => true,
        "array_filter" => true,
        "compact" => true,
        "strtolower" => true,
        "strtoupper" => true,
        "get_object_vars" => true,
    ];

    private static $ignore_classes = [
        \ReflectionClass::class => true,
        \ReflectionMethod::class => true,
    ];

    private static $ignore_constants = [
        'false' => true,
        'true'  => true,
        'null'  => true,
    ];

    public static function isFunctionIgnored($func)
    {
        return isset(self::$ignore_functions[$func]);
    }

    public static function isClassIgnored($class)
    {
        return isset(self::$ignore_classes[$class]);
    }

    public static function isConstIgnored($const)
    {
        return isset(self::$ignore_constants[$const]);
    }

    public static function ignoreClass($class)
    {
        self::$ignore_classes[$class] = true;
    }

    public static function ignoreConstant($constant)
    {
        self::$ignore_constants[$constant] = true;
    }

    public static function ignoreFunction($function)
    {
        self::$ignore_functions[$function] = true;
    }

    private $filename;

    private $disable_const_rewrite_level = 0;
    private $disable_func_call_inside_encapsed_rewrite_level = 0;

    private $in_interface = false;
    private $in_closure_level = 0;
    private $has_yield = false;
    private $cur_class = '';

    /** @var bool Whether or not parser is good (e.g. can parse "callFunc()()" properly) */
    private $parser_is_ok = false;

    public function __construct($filename)
    {
        $this->parser_is_ok = (PHP_MAJOR_VERSION >= 7);
        $this->filename = realpath($filename);
        if (strpos($this->filename, SOFTMOCKS_ROOT_PATH) === 0) {
            $this->filename = substr($this->filename, strlen(SOFTMOCKS_ROOT_PATH));
        }
    }

    private static function getNamespaceArg()
    {
        return new \PhpParser\Node\Arg(
            new \PhpParser\Node\Expr\ConstFetch(
                new \PhpParser\Node\Name('__NAMESPACE__')
            )
        );
    }

    public function enterNode(\PhpParser\Node $Node)
    {
        $callback = [$this, 'before' . ucfirst($Node->getType())];
        if (is_callable($callback)) {
            return call_user_func_array($callback, [$Node]);
        }
        return null;
    }

    public function leaveNode(\PhpParser\Node $Node)
    {
        $callback = [$this, 'rewrite' . ucfirst($Node->getType())];
        if (is_callable($callback)) {
            return call_user_func_array($callback, [$Node]);
        }
        return null;
    }

    // Cannot rewrite constants that are used as default values in function arguments
    public function beforeParam()
    {
        $this->disable_const_rewrite_level++;
    }

    public function rewriteParam()
    {
        $this->disable_const_rewrite_level--;
    }

    // Cannot rewrite constants that are used as default values in constant declarations
    public function beforeConst()
    {
        $this->disable_const_rewrite_level++;
    }

    public function rewriteConst()
    {
        $this->disable_const_rewrite_level--;
    }

    // Cannot rewrite constants that are used as default values in property declarations
    public function beforeStmt_PropertyProperty()
    {
        $this->disable_const_rewrite_level++;
    }

    public function rewriteStmt_PropertyProperty()
    {
        $this->disable_const_rewrite_level--;
    }

    // Cannot rewrite constants that are used as default values in static variable declarations
    public function beforeStmt_StaticVar()
    {
        $this->disable_const_rewrite_level++;
    }

    public function rewriteStmt_StaticVar()
    {
        $this->disable_const_rewrite_level--;
    }

    public function beforeStmt_Interface(\PhpParser\Node\Stmt\Interface_ $Node)
    {
        $this->cur_class = $Node->name;
        $this->in_interface = true;
    }

    public function rewriteStmt_Interface()
    {
        $this->cur_class = false;
        $this->in_interface = false;
    }

    public function rewriteScalar_MagicConst_Dir()
    {
        $String = new \PhpParser\Node\Scalar\String_(dirname($this->filename));
        if ($this->filename[0] === '/') { // absolute path
            return $String;
        }

        return new \PhpParser\Node\Expr\BinaryOp\Concat(
            new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('SOFTMOCKS_ROOT_PATH')),
            $String
        );
    }

    public function rewriteScalar_MagicConst_File()
    {
        $String = new \PhpParser\Node\Scalar\String_($this->filename);
        if ($this->filename[0] === '/') { // absolute path
            return $String;
        }

        return new \PhpParser\Node\Expr\BinaryOp\Concat(
            new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('SOFTMOCKS_ROOT_PATH')),
            $String
        );
    }

    public function rewriteExpr_Include(\PhpParser\Node\Expr\Include_ $Node)
    {
        $Node->expr = new \PhpParser\Node\Expr\StaticCall(
            new \PhpParser\Node\Name("\\" . SoftMocks::class),
            "rewrite",
            [new \PhpParser\Node\Arg($Node->expr)]
        );
    }

    public function rewriteExpr_Exit(\PhpParser\Node\Expr\Exit_ $Node)
    {
        $args = [];
        if ($Node->expr !== null) {
            $args[] = new \PhpParser\Node\Arg($Node->expr);
        }

        $NewNode = new \PhpParser\Node\Expr\StaticCall(
            new \PhpParser\Node\Name("\\" . SoftMocks::class),
            "callExit",
            $args
        );
        $NewNode->setLine($Node->getLine());

        return $NewNode;
    }

    public function beforeStmt_ClassMethod()
    {
        $this->in_closure_level = 0;
        $this->has_yield = false;
    }

    public function beforeExpr_Closure()
    {
        $this->in_closure_level++;
    }

    public function rewriteExpr_Closure(\PhpParser\Node\Expr\Closure $Node)
    {
        $this->in_closure_level--;
        return $Node;
    }

    public function beforeExpr_Yield()
    {
        if ($this->in_closure_level === 0) {
            $this->has_yield = true;
        }
    }

    public function beforeExpr_YieldFrom()
    {
        if ($this->in_closure_level === 0) {
            $this->has_yield = true;
        }
    }

    public function beforeStmt_Class(\PhpParser\Node\Stmt\Class_ $Node)
    {
        $this->cur_class = $Node->name;
    }

    public function rewriteStmt_Class()
    {
        $this->cur_class = null;
    }

    public function beforeStmt_Trait(\PhpParser\Node\Stmt\Trait_ $Node)
    {
        $this->cur_class = $Node->name;
    }

    public function rewriteStmt_Trait()
    {
        $this->cur_class = null;
    }

    public function rewriteStmt_ClassMethod(\PhpParser\Node\Stmt\ClassMethod $Node)
    {
        if ($this->in_interface) {
            return null;
        }

        // if (false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked("self"::class, static::class, __FUNCTION__))) {
        //     $params = [/* variables with references to them */];
        //     $mm_func_args = func_get_args();
        //     $variadic_params_idx = '' || '<idx_of variadic_params>'
        //     return eval($__softmocksvariableforcode);
        // }/** @codeCoverageIgnore */
        $static = new \PhpParser\Node\Arg(
            new \PhpParser\Node\Expr\ClassConstFetch(
                new \PhpParser\Node\Name("static"),
                "class"
            )
        );

        $function = new \PhpParser\Node\Expr\ConstFetch(
            new \PhpParser\Node\Name("__FUNCTION__")
        );

        $params_arr = [];
        $variadic_params_idx = null;
        $last_param_idx = sizeof($Node->params) - 1;
        if ($last_param_idx >= 0 && $Node->params[$last_param_idx]->variadic) {
            $variadic_params_idx = $last_param_idx;
        }
        foreach ($Node->params as $Par) {
            $params_arr[] = new \PhpParser\Node\Expr\ArrayItem(
                new \PhpParser\Node\Expr\Variable($Par->name),
                null,
                $Par->byRef
            );
        }

        $body_stmts = [
            new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("mm_func_args"),
                new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name("func_get_args"))
            ),
            new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("params"),
                new \PhpParser\Node\Expr\Array_($params_arr)
            ),
            new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("variadic_params_idx"),
                new \PhpParser\Node\Scalar\String_($variadic_params_idx)
            ),
        ];

        // generators cannot return values,
        // we need special code handling them because yield cannot be used inside eval
        // we get something like the following:
        //
        //     $mm_callback = SoftMocks::getMockForGenerator();
        //     foreach ($mm_callback(...) as $mm_val) { yield $mm_val; }
        //
        // also functions with void return type declarations cannot return values
        if ($this->has_yield) {
            $args = [$static, $function];

            $body_stmts[] = new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("mm_callback"),
                new \PhpParser\Node\Expr\StaticCall(
                    new \PhpParser\Node\Name("\\" . SoftMocks::class),
                    "getMockForGenerator",
                    $args
                )
            );

            $func_call_args = [];
            foreach ($Node->params as $Par) {
                $func_call_args[] = new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\Variable($Par->name));
            }

            $val = new \PhpParser\Node\Expr\Variable("mm_val");

            $body_stmts[] = new \PhpParser\Node\Stmt\Foreach_(
                new \PhpParser\Node\Expr\FuncCall(
                    new \PhpParser\Node\Expr\Variable("mm_callback"),
                    $func_call_args
                ),
                $val,
                [
                    'byRef' => $Node->byRef,
                    'stmts' => [new \PhpParser\Node\Expr\Yield_($val)],
                ]
            );

            $body_stmts[] = new \PhpParser\Node\Stmt\Return_();
        } else {
            $args = [
                new \PhpParser\Node\Arg(
                    new \PhpParser\Node\Expr\ClassConstFetch(
                        new \PhpParser\Node\Name($this->cur_class ?: 'self'),
                        "class"
                    )
                ),
                $static,
                $function,
            ];

            $eval = new \PhpParser\Node\Expr\Eval_(
                new \PhpParser\Node\Expr\Variable("__softmocksvariableforcode")
            );

            if ($Node->returnType === 'void') {
                $body_stmts[] = $eval;
                $body_stmts[] = new \PhpParser\Node\Stmt\Return_();
            } else {
                $body_stmts[] = new \PhpParser\Node\Stmt\Return_($eval);
            }
        }
        $body_stmts[] = new \PhpParser\Node\Name("/** @codeCoverageIgnore */");

        $MockCheck = new \PhpParser\Node\Stmt\If_(
            new \PhpParser\Node\Expr\BinaryOp\NotIdentical(
                new \PhpParser\Node\Expr\ConstFetch(
                    new \PhpParser\Node\Name("false")
                ),
                new \PhpParser\Node\Expr\Assign(
                    new \PhpParser\Node\Expr\Variable("__softmocksvariableforcode"),
                    new \PhpParser\Node\Expr\StaticCall(
                        new \PhpParser\Node\Name("\\" . SoftMocks::class),
                        $this->has_yield ? "isGeneratorMocked" : "isMocked",
                        $args
                    )
                )
            ),
            [
                'stmts' => $body_stmts,
            ]
        );

        if (is_array($Node->stmts)) {
            array_unshift($Node->stmts, $MockCheck);
        } else if (!$Node->isAbstract()) {
            $Node->stmts = [$MockCheck];
        }
    }

    private static $can_ref = [
        'Expr_Variable' => true,
        'Expr_PropertyFetch' => true,
        'Expr_StaticPropertyFetch' => true,
    ];

    /**
     * Determines whether or not a parameter can be reference (e.g. vars can be referenced, while function calls cannot)
     *
     * @param \PhpParser\Node\Expr $value
     * @return bool
     */
    private static function canRef($value)
    {
        $type = $value->getType();
        if (isset(self::$can_ref[$type])) {
            return true;
        } else if ($value instanceof \PhpParser\Node\Expr\ArrayDimFetch) {
            if (!self::canRef($value->var)) {
                return false;
            }

            // an ugly hack for ArrayAccess objects that are used as "$this['something']"
            if ($value->var instanceof \PhpParser\Node\Expr\Variable && $value->var->name == 'this') {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param \PhpParser\Node\Arg[] $node_args
     * @param array $arg_is_ref    array(arg_idx => bool)   whether or not the specified argument accepts reference
     * @return \PhpParser\Node\Expr\Array_|\PhpParser\Node\Expr\FuncCall
     */
    private static function nodeArgsToArray($node_args, $arg_is_ref = [])
    {
        $arr_args = [];
        $i = 0;

        foreach ($node_args as $arg) {
            /** @var \PhpParser\Node\Expr\ArrayItem $arg */
            $is_ref = false;

            if (isset($arg_is_ref[$i]) && !$arg_is_ref[$i]) {
                $is_ref = false;
            } else if (self::canRef($arg->value)) {
                $is_ref = true;
            }

            if ($arg->unpack) {
                if ($i !== count($node_args) - 1) {
                    throw new \InvalidArgumentException("Unpackable argument '" . var_export($arg, true) . "' should be last");
                }
                $unpacked_arg = clone $arg;
                $unpacked_arg->unpack = false;
                return new \PhpParser\Node\Expr\FuncCall(
                    new \PhpParser\Node\Name(['', 'array_merge']),
                    [new \PhpParser\Node\Expr\Array_($arr_args), $unpacked_arg]
                );
            }
            $arr_args[] = new \PhpParser\Node\Expr\ArrayItem(
                $arg->value,
                null,
                $is_ref
            );

            $i++;
        }

        return new \PhpParser\Node\Expr\Array_($arr_args);
    }

    private static function nodeNameToArg($name)
    {
        if (is_scalar($name)) {
            $name = new \PhpParser\Node\Scalar\String_($name);
        } else if ($name instanceof \PhpParser\Node\Name) {
            return new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\ClassConstFetch($name, 'class'));
        }

        return new \PhpParser\Node\Arg($name);
    }

    public function rewriteStmt_Function(\PhpParser\Node\Stmt\Function_ $Node)
    {
        // if (false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isFuncMocked(__FUNCTION__))) {
        //     $params = [/* variables with references to them */];
        //     $mm_func_args = func_get_args();
        //     $variadic_params_idx = '' || '<idx_of variadic_params>'
        //     return eval($__softmocksvariableforcode);
        // }/** @codeCoverageIgnore */

        $function = new \PhpParser\Node\Expr\ConstFetch(
            new \PhpParser\Node\Name("__FUNCTION__")
        );

        $params_arr = [];
        $variadic_params_idx = null;
        $last_param_idx = sizeof($Node->params) - 1;
        if ($last_param_idx >= 0 && $Node->params[$last_param_idx]->variadic) {
            $variadic_params_idx = $last_param_idx;
        }
        foreach ($Node->params as $Par) {
            $params_arr[] = new \PhpParser\Node\Expr\ArrayItem(
                new \PhpParser\Node\Expr\Variable($Par->name),
                null,
                $Par->byRef
            );
        }

        $body_stmts = [
            new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("mm_func_args"),
                new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name("func_get_args"))
            ),
            new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("params"),
                new \PhpParser\Node\Expr\Array_($params_arr)
            ),
            new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("variadic_params_idx"),
                new \PhpParser\Node\Scalar\String_($variadic_params_idx)
            ),
        ];

        // generators cannot return values,
        // we need special code handling them because yield cannot be used inside eval
        // we get something like the following:
        //
        //     $mm_callback = SoftMocks::getMockForGenerator();
        //     foreach ($mm_callback(...) as $mm_val) { yield $mm_val; }
        //
        // also functions with void return type declarations cannot return values
        if ($this->has_yield) {
            $body_stmts[] = new \PhpParser\Node\Expr\Assign(
                new \PhpParser\Node\Expr\Variable("mm_callback"),
                new \PhpParser\Node\Expr\StaticCall(
                    new \PhpParser\Node\Name("\\" . SoftMocks::class),
                    "getMockForGeneratorFunction",
                    [$function]
                )
            );

            $func_call_args = [];
            foreach ($Node->params as $Par) {
                $func_call_args[] = new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\Variable($Par->name));
            }

            $val = new \PhpParser\Node\Expr\Variable("mm_val");

            $body_stmts[] = new \PhpParser\Node\Stmt\Foreach_(
                new \PhpParser\Node\Expr\FuncCall(
                    new \PhpParser\Node\Expr\Variable("mm_callback"),
                    $func_call_args
                ),
                $val,
                [
                    'byRef' => $Node->byRef,
                    'stmts' => [new \PhpParser\Node\Expr\Yield_($val)],
                ]
            );

            $body_stmts[] = new \PhpParser\Node\Stmt\Return_();
        } else {
            $eval = new \PhpParser\Node\Expr\Eval_(
                new \PhpParser\Node\Expr\Variable("__softmocksvariableforcode")
            );

            if ($Node->returnType === 'void') {
                $body_stmts[] = $eval;
                $body_stmts[] = new \PhpParser\Node\Stmt\Return_();
            } else {
                $body_stmts[] = new \PhpParser\Node\Stmt\Return_($eval);
            }
        }
        $body_stmts[] = new \PhpParser\Node\Name("/** @codeCoverageIgnore */");

        $MockCheck = new \PhpParser\Node\Stmt\If_(
            new \PhpParser\Node\Expr\BinaryOp\NotIdentical(
                new \PhpParser\Node\Expr\ConstFetch(
                    new \PhpParser\Node\Name("false")
                ),
                new \PhpParser\Node\Expr\Assign(
                    new \PhpParser\Node\Expr\Variable("__softmocksvariableforcode"),
                    new \PhpParser\Node\Expr\StaticCall(
                        new \PhpParser\Node\Name("\\" . SoftMocks::class),
                        $this->has_yield ? "isGeneratorMockedFunction" : "isFuncMocked",
                        [$function]
                    )
                )
            ),
            [
                'stmts' => $body_stmts,
            ]
        );

        if (is_array($Node->stmts)) {
            array_unshift($Node->stmts, $MockCheck);
        }
    }

    public function rewriteExpr_FuncCall(\PhpParser\Node\Expr\FuncCall $Node)
    {
        if ($this->disable_func_call_inside_encapsed_rewrite_level > 0) {
            return null;
        }

        $arg_is_ref = [];

        $can_be_internal_function = true;

        if ($Node->name instanceof \PhpParser\Node\Name) {
            $str = $Node->name->toString();
            if (isset(self::$ignore_functions[$str])) {
                return null;
            }

            if (isset(SoftMocks::$internal_functions[$str])) {
                foreach ((new \ReflectionFunction($str))->getParameters() as $Param) {
                    $arg_is_ref[] = $Param->isPassedByReference();
                }
            } else {
                $can_be_internal_function = false;
            }

            $name = new \PhpParser\Node\Scalar\String_($str);
        } else { // Expr
            $name = $Node->name;
        }

        if (!$can_be_internal_function) {
            return null; // user-defined functions are mocked using usual "if (...) { return eval(...); }"
        }

        /*
        // TODO: write SoftMocks::getFunction() first

        if ($this->parser_is_ok) {
            $Node->name = new \PhpParser\Node\Expr\StaticCall(
                new \PhpParser\Node\Name("\\" . SoftMocks::class),
                "getFunction",
                [
                    self::getNamespaceArg(),
                    $Node->name
                ]
            );
            return $Node;
        }
        */

        $NewNode = new \PhpParser\Node\Expr\StaticCall(
            new \PhpParser\Node\Name("\\" . SoftMocks::class),
            "callFunction",
            [
                self::getNamespaceArg(),
                $name,
                self::nodeArgsToArray($Node->args, $arg_is_ref),
            ]
        );
        $NewNode->setLine($Node->getLine());

        return $NewNode;
    }

    public function rewriteExpr_ConstFetch(\PhpParser\Node\Expr\ConstFetch $Node)
    {
        if ($this->disable_const_rewrite_level > 0) {
            return null;
        }

        $name = $Node->name->toString();

        if (isset(self::$ignore_constants[strtolower($name)])) {
            return null;
        }

        $NewNode = new \PhpParser\Node\Expr\StaticCall(
            new \PhpParser\Node\Name("\\" . SoftMocks::class),
            "getConst",
            [
                self::getNamespaceArg(),
                self::nodeNameToArg($name),
            ]
        );

        $NewNode->setLine($Node->getLine());
        return $NewNode;
    }

    public function rewriteExpr_ClassConstFetch(\PhpParser\Node\Expr\ClassConstFetch $Node)
    {
        if ($this->disable_const_rewrite_level > 0 || strtolower($Node->name) == 'class') {
            return null;
        }

        $params = [
            self::nodeNameToArg($Node->class),
            self::nodeNameToArg($Node->name),
        ];
        if ($this->cur_class) {
            $params[] = new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\ClassConstFetch(new \PhpParser\Node\Name('self'), 'class'));
        } else {
            $params[] = new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('null')));
        }

        $NewNode = new \PhpParser\Node\Expr\StaticCall(
            new \PhpParser\Node\Name("\\" . SoftMocks::class),
            "getClassConst",
            $params
        );

        $NewNode->setLine($Node->getLine());
        return $NewNode;
    }

    public function beforeScalar_Encapsed()
    {
        $this->disable_func_call_inside_encapsed_rewrite_level++;
    }

    public function rewriteScalar_Encapsed()
    {
        $this->disable_func_call_inside_encapsed_rewrite_level--;
    }
}
