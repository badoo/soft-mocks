<?php
/**
 * Mocks core that rewrites code
 * @author Yuriy Nasretdinov <y.nasretdinov@corp.badoo.com>
 * @author Oleg Efimov <o.efimov@corp.badoo.com>
 * @author Kirill Abrosimov <k.abrosimov@corp.badoo.com>
 */

namespace Badoo;

use PhpParser\Node\Scalar\String_;

class SoftMocksFunctionCreator
{
    public static function run($obj, $class, $params, $mocks)
    {
        if ($mocks['code'] instanceof \Closure) {
            $new_func = $mocks['code'];
        } else {
            $code = "return function(" . $mocks['args'] . ") use (\$params) { " . $mocks['code'] . " };";
            $func = eval($code);
            $new_func = \Closure::bind($func, $obj, $class);
        }

        return call_user_func_array($new_func, $params);
    }
}

class SoftMocksPrinter extends \PhpParser\PrettyPrinter\Standard
{
    private $cur_ln;
    protected $nl = "\n";

    /**
     * Pretty prints an array of nodes (statements) and indents them optionally.
     *
     * @param \PhpParser\Node[] $nodes  Array of nodes
     * @param bool   $indent Whether to indent the printed nodes
     *
     * @return string Pretty printed statements
     */
    protected function pStmts(array $nodes, bool $indent = true) : string
    {
        if ($indent) {
            $this->indent();
        }

        $result = '';
        foreach ($nodes as $node) {
            $row = "";

            $cur_ln = $this->cur_ln;

            $comments = $node->getComments();
            $comments = !empty($comments) ? ($this->nl . $this->pComments($node->getComments())) : '';
            $this->cur_ln += mb_substr_count($comments, "\n");

            if ($node->getLine() > $this->cur_ln) {
                $comments .= $this->nl;
                $row .= str_repeat($this->nl, $node->getLine() - $this->cur_ln - 1);
            }

            $row .= $comments . $this->p($node);

            $this->cur_ln = $cur_ln + mb_substr_count($row, "\n"); // get rid of cur_ln modifications in deeper context

            $result .= $row;
        }

        if ($indent) {
            $this->outdent();
        }

        return $result;
    }

    protected function pComments(array $comments) : string
    {
        $formattedComments = [];

        foreach ($comments as $comment) {
            $reformattedText = $comment->getReformattedText();
            if (strpos($reformattedText, '/**') === 0) {
                $formattedComments[] = $reformattedText;
            }
        }

        return !empty($formattedComments) ? implode($this->nl, $formattedComments) : '';
    }

    protected function pCommaSeparatedMultiline(array $nodes, bool $trailingComma) : string
    {
        $this->indent();
        $result = '';
        $lastIdx = count($nodes) - 1;
        foreach ($nodes as $idx => $node) {
            if ($node !== null) {
                $comments = $node->getComments();
                if ($comments) {
                    $result .= $this->pComments($comments);
                }

                $result .= $this->nl . $this->p($node);
            } else {
                $result .= $this->nl;
            }
            if ($trailingComma || $idx !== $lastIdx) {
                $result .= ',';
            }
        }

        $this->outdent();
        return $result;
    }

    /**
     * Handles (and removes) no-indent and doc-string-end tokens.
     *
     * @param string $str
     * @return string
     */
    protected function handleMagicTokens(string $str) : string {
        $str = str_replace($this->docStringEndToken . ';', ';', $str);
        $str = str_replace($this->docStringEndToken, "\n", $str);

        return $str;
    }

    public function prettyPrintFile(array $stmts) : string
    {
        $this->cur_ln = 1;
        $this->preprocessNodes($stmts);
        $result = $this->pStmts($stmts, false);
        $result = $this->handleMagicTokens($result);
        $prefix = '<?php';
        if ($result && $result[0] !== "\n") {
            $prefix .= ' ';
        }
        return "{$prefix}{$result}";
    }

    protected function pExpr_Array(\PhpParser\Node\Expr\Array_ $node)
    {
        $is_short = $this->options['shortArraySyntax'] ? \PhpParser\Node\Expr\Array_::KIND_SHORT : \PhpParser\Node\Expr\Array_::KIND_LONG;
        $syntax = $node->getAttribute(
            'kind',
            $is_short
        );
        if ($syntax === \PhpParser\Node\Expr\Array_::KIND_SHORT) {
            $res = '[' . $this->pMaybeMultiline($node->items, true);
            $suffix = ']';
        } else {
            $res = 'array(' . $this->pMaybeMultiline($node->items, true);
            $suffix = ')';
        }
        $prefix = "";
        if (!$this->areNodesSingleLine($node->items)) {
            if ($node->getAttribute('endLine') - ($node->getLine() + mb_substr_count($res, "\n")) >= 0) {
                $prefix = str_repeat("\n", $node->getAttribute('endLine') - ($node->getLine() + mb_substr_count($res, "\n")));
            }
        }
        $res .= $prefix . $suffix;
        return $res;
    }

    /**
     * @param \PhpParser\NodeAbstract[] $nodes
     * @return bool
     */
    protected function areNodesSingleLine(array $nodes)
    {
        if (empty($nodes)) {
            return true;
        }
        $first_line = $last_line = null;
        foreach ($nodes as $node) {
            if ($node) {
                if ($first_line === null) {
                    $first_line = $node->getAttribute('startLine');
                }
                $last_line = $node->getAttribute('endLine');
            }
        }
        return ($first_line === null) || ($first_line === $last_line);
    }

    /**
     * @param \PhpParser\NodeAbstract[] $nodes
     * @param bool $trailingComma
     * @return bool|string
     */
    protected function pMaybeMultiline(array $nodes, $trailingComma = false)
    {
        if ($this->areNodesSingleLine($nodes)) {
            return $this->pCommaSeparated($nodes);
        } else {
            return $this->pCommaSeparatedMultiline($nodes, $trailingComma);
        }
    }

    protected function pExpr_Closure(\PhpParser\Node\Expr\Closure $node)
    {
        $this->cur_ln = $node->getLine();
        return ($node->static ? 'static ' : '')
            . 'function ' . ($node->byRef ? '&' : '')
            . '(' . $this->pCommaSeparated($node->params) . ')'
            . (!empty($node->uses) ? ' use(' . $this->pCommaSeparated($node->uses) . ')' : '')
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . ' {' . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_Namespace(\PhpParser\Node\Stmt\Namespace_ $node)
    {
        $this->cur_ln = $node->getLine();
        if ($this->canUseSemicolonNamespaces) {
            return 'namespace ' . $this->p($node->name) . ';' . $this->pStmts($node->stmts, false);
        } else {
            return 'namespace' . (null !== $node->name ? ' ' . $this->p($node->name) : '')
                . ' {' . $this->pStmts($node->stmts) . '}';
        }
    }

    protected function pStmt_Interface(\PhpParser\Node\Stmt\Interface_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'interface ' . $node->name
            . (!empty($node->extends) ? ' extends ' . $this->pCommaSeparated($node->extends) : '')
            . '{' . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_Class(\PhpParser\Node\Stmt\Class_ $node)
    {
        $this->cur_ln = $node->getLine();
        return $this->pClassCommon($node, ' ' . $node->name);
    }

    protected function pStmt_Trait(\PhpParser\Node\Stmt\Trait_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'trait ' . $node->name
            . '{' . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_TraitUse(\PhpParser\Node\Stmt\TraitUse $node)
    {
        return 'use ' . $this->pCommaSeparated($node->traits)
            . (empty($node->adaptations) ? ';' : ' {' . $this->pStmts($node->adaptations) . '}');
    }

    protected function pStmt_ClassMethod(\PhpParser\Node\Stmt\ClassMethod $node)
    {
        $ret = '';
        if ($node->attrGroups) {
            $ret = $this->pStmts($node->attrGroups);
        }

        $this->cur_ln = $node->getLine();
        return $ret
            . $this->pModifiers($node->flags)
            . 'function ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pCommaSeparated($node->params) . ')'
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . (null !== $node->stmts ? '{' . $this->pStmts($node->stmts) . '}' : ';');
    }

    protected function pStmt_Function(\PhpParser\Node\Stmt\Function_ $node)
    {
        $ret = '';
        if ($node->attrGroups) {
            $ret = $this->pStmts($node->attrGroups);
        }

        $this->cur_ln = $node->getLine();
        return $ret
            . 'function ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pCommaSeparated($node->params) . ')'
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . '{' . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_If(\PhpParser\Node\Stmt\If_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'if (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . '}'
            . ($node->elseifs ? ' ' . $this->pStmts($node->elseifs, ' ') : '')
            . (null !== $node->else ? ' ' . $this->p($node->else) : '');
    }

    protected function pStmt_ElseIf(\PhpParser\Node\Stmt\ElseIf_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'elseif (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_Else(\PhpParser\Node\Stmt\Else_ $node)
    {
        return 'else {' . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_For(\PhpParser\Node\Stmt\For_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'for ('
            . $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
            . $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
            . $this->pCommaSeparated($node->loop)
            . ') {' . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_Foreach(\PhpParser\Node\Stmt\Foreach_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'foreach (' . $this->p($node->expr) . ' as '
            . (null !== $node->keyVar ? $this->p($node->keyVar) . ' => ' : '')
            . ($node->byRef ? '&' : '') . $this->p($node->valueVar) . ') {'
            . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_While(\PhpParser\Node\Stmt\While_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'while (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . '}';
    }

    protected function pStmt_Do(\PhpParser\Node\Stmt\Do_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'do {' . $this->pStmts($node->stmts)
            . '} while (' . $this->p($node->cond) . ');';
    }

    protected function pStmt_Switch(\PhpParser\Node\Stmt\Switch_ $node)
    {
        $this->cur_ln = $node->getLine();
        return 'switch (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->cases) . '}';
    }

    protected function pStmt_Case(\PhpParser\Node\Stmt\Case_ $node)
    {
        $this->cur_ln = $node->getLine();
        return (null !== $node->cond ? 'case ' . $this->p($node->cond) : 'default') . ':'
            . $this->pStmts($node->stmts);
    }

    protected function pStmt_TryCatch(\PhpParser\Node\Stmt\TryCatch $node)
    {
        $this->cur_ln = $node->getLine();
        return 'try {' . $this->pStmts($node->stmts) . '}'
            . ($node->catches ? ' ' . $this->pImplode($node->catches, ' ') : '')
            . ($node->finally !== null ? ' finally {' . $this->pStmts($node->finally->stmts) . '}' : '');
    }

    protected function pStmt_Catch(\PhpParser\Node\Stmt\Catch_ $node)
    {
        if ($node->var) {
            return 'catch (' . $this->pImplode($node->types, ' | ') . ' '
            . $this->p($node->var)
            . ') {' . $this->pStmts($node->stmts) . '}';
        } else { // handle the non-capturing catch
            return 'catch (' . $this->pImplode($node->types, ' | ')
            . ') {' . $this->pStmts($node->stmts) . '}';
        }
    }

    // Helpers

    protected function pClassCommon(\PhpParser\Node\Stmt\Class_ $node, $afterClassToken)
    {
        $ret = '';
        if ($node->attrGroups) {
            $ret = $this->pStmts($node->attrGroups);
        }

        return $ret
            . $this->pModifiers($node->flags)
            . 'class' . $afterClassToken
            . (null !== $node->extends ? ' extends ' . $this->p($node->extends) : '')
            . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
            . '{' . $this->pStmts($node->stmts) . '}';
    }

    protected function pEncapsList(array $encapsList, $quote)
    {
        $bak_line = $this->cur_ln;
        $return = '';
        foreach ($encapsList as $element) {
            if ($element instanceof \PhpParser\Node\Scalar\EncapsedStringPart) {
                $element = $element->value;
            }
            if (is_string($element)) {
                $return .= addcslashes($element, "\n\r\t\f\v$" . $quote . "\\");
            } else {
                $return .= '{' . trim($this->p($element)) . '}';
            }
        }
        $this->cur_ln = $bak_line + mb_substr_count($return, "\n");

        return $return;
    }
}

class SoftMocksParseError extends \PhpParser\Error
{
    public function __construct(string $file_path, \PhpParser\Error $Error)
    {
        parent::__construct("File: {$file_path}, message: {$Error->getMessage()}", $Error->getAttributes());
    }
}

class SoftMocksParseErrorHandler implements \PhpParser\ErrorHandler
{
    private $orig_file;

    public function __construct($orig_file)
    {
        $this->orig_file = $orig_file;
    }

    public function handleError(\PhpParser\Error $error)
    {
        throw new SoftMocksParseError($this->orig_file, $error);
    }
}

class SoftMocks
{
    const MOCKS_CACHE_TOUCHTIME = 86400; // 1 day

    private static $rewrite_cache = [/* source => target */];
    private static $orig_paths = [/* target => source */];

    private static $mocks_dir_version;

    private static $error_descriptions = [
        E_ERROR => "Error",
        E_WARNING => "Warning",
        E_PARSE => "Parse Error",
        E_NOTICE => "Notice",
        E_CORE_ERROR => "Core Error",
        E_CORE_WARNING => "Core Warning",
        E_COMPILE_ERROR => "Compile Error",
        E_COMPILE_WARNING => "Compile Warning",
        E_USER_ERROR => "User Error",
        E_USER_WARNING => "User Warning",
        E_USER_NOTICE => "User Notice",
        E_STRICT => "Strict Notice",
        E_RECOVERABLE_ERROR => "Recoverable Error",
    ];

    private static $vendor_path;
    /** for create new files when parser version changed */
    private static $parser_version;
    private static $ignore = [];

    public static $internal_functions = [];

    private static $mocks = [];
    public static $mocks_by_name = [];

    private static $func_mocks = [];
    public static $func_mocks_by_name = [];
    private static $internal_func_mocks = []; // internal mocks that cannot be changed
    private static $internal_func_mocks_by_name = [];

    private static $generator_mocks = []; // mocks for generators

    private static $new_mocks = []; // mocks for "new" operator
    private static $lang_construct_mocks = [];
    private static $constant_mocks = [];
    private static $removed_constants = [];
    public static $class_const_mocks_by_name = [];

    // variables for pause/resume feature
    private static $paused_mocks = [];
    private static $paused_generator_mocks = [];
    private static $paused_func_mocks = [];
    private static $paused_lang_construct_mocks = [];
    private static $paused_constant_mocks = [];
    private static $paused_removed_constants = [];
    private static $paused_new_mocks = [];
    private static $paused_temp_disable = false;
    // flag indicates that SoftMocks are on pause
    private static $paused = false;

    private static $debug = false;

    private static $temp_disable = false;

    const LANG_CONSTRUCT_EXIT = 'exit';

    private static $project_path;
    private static $rewrite_internal = false;
    private static $mocks_cache_path;
    private static $ignore_sub_paths = [
        '/phpunit/' => '/phpunit/',
        '/php-parser/' => '/php-parser/',
    ];
    private static $base_paths = [];
    private static $prepare_for_rewrite_callback;
    private static $lock_file_path;
    private static $backtrace_ignored_method_calls = [
        [\PHPUnit\Framework\TestCase::class, 'run'],
        [\PHPUnit\Framework\TestCase::class, 'runTest'],
    ];

    protected static function getEnvironment($key)
    {
        return \getenv($key);
    }

    /**
     * Init soft mocks
     * @throws \RuntimeException
     */
    public static function init()
    {
        if (!self::$mocks_cache_path) {
            $mocks_cache_path = (string)static::getEnvironment('SOFT_MOCKS_CACHE_PATH');
            if (!$mocks_cache_path) {
                $mocks_cache_path = realpath(sys_get_temp_dir()) . '/mocks/';
            }
            self::setMocksCachePath($mocks_cache_path);
            self::$lock_file_path = $mocks_cache_path . '/soft_mocks_rewrite.lock';
        }
        // todo constant will be removed in next major release, because it's like project path.
        if (!defined('SOFTMOCKS_ROOT_PATH')) {
            /**
             * @deprecated use \Badoo\SoftMocks::setProjectPath() instead
             * @see \Badoo\SoftMocks::setProjectPath()
             */
            define('SOFTMOCKS_ROOT_PATH', '/');
        }

        if (!empty(static::getEnvironment('SOFT_MOCKS_DEBUG'))) {
            self::$debug = true;
        }

        self::$func_mocks['call_user_func_array'] = [
            'args' => '', 'code' => 'return \\' . self::class . '::call("", $params[0], $params[1]);',
        ];
        self::$func_mocks['call_user_func'] = [
            'args' => '', 'code' => '$func = array_shift($params); return \\' . self::class . '::call("", $func, $params);',
        ];
        self::$func_mocks['is_callable'] = [
            'args' => '$arg', 'code' => 'return \\' . self::class . '::isCallable($arg);',
        ];
        self::$func_mocks['function_exists'] = [
            'args' => '$arg', 'code' => 'return \\' . self::class . '::isCallable($arg);',
        ];
        self::$func_mocks['constant'] = [
            'args' => '$constant', 'code' => 'return \\' . self::class . '::getConst("", $constant);',
        ];
        self::$func_mocks['defined'] = [
            'args' => '$constant', 'code' => 'return \\' . self::class . '::constDefined($constant);',
        ];
        self::$func_mocks['debug_backtrace'] = [
            'args' => '',
            'code' => function () {
                $params = func_get_args();

                $limit = 0;
                $provide_object = true;
                $ignore_args = false;

                if (isset($params[0])) {
                    if ($params[0] === false) {
                        $provide_object = false;
                    } else if ($params[0] === true) {
                        $provide_object = true;
                    } else {
                        $provide_object = $params[0] & DEBUG_BACKTRACE_PROVIDE_OBJECT;
                        $ignore_args = $params[0] & DEBUG_BACKTRACE_IGNORE_ARGS;
                    }
                }
                if (isset($params[1])) {
                    $limit = $params[1];
                }

                $result = debug_backtrace();

                // remove our part of backtrace
                while (count($result) > 0) {
                    if (isset($result[0]['class']) && $result[0]['class'] === self::class
                        && isset($result[0]['function']) && $result[0]['function'] === 'callFunction') {
                        array_shift($result);
                        break;
                    }

                    array_shift($result);
                }

                // remove calls that occur inside our file
                $result = array_values(
                    array_filter(
                        $result,
                        function ($el) { return !isset($el['file']) || $el['file'] !== __FILE__; }
                    )
                );

                $unset_indices = [];

                // replace paths
                foreach ($result as $i => &$p) {
                    if (isset($p["file"])) {
                        $p["file"] = self::replaceFilename($p["file"], true);
                        if ($p["file"] && $p["file"][0] !== '/') {
                            $p["file"] = SOFTMOCKS_ROOT_PATH . $p['file'];
                        }
                    }

                    if (isset($p['class']) && $p['class'] == self::class && isset($p['args'])) {
                        $args = $p['args'];

                        if ($p['function'] === 'callFunction') {
                            unset($p['class']);
                            unset($p['type']);
                            $p['function'] = $args[1];
                            $p['args'] = $args[2];
                            $unset_indices[] = $i - 1; // reflection adds another line in trace that is not needed
                        } else if ($p['function'] === 'callMethod') {
                            $p['object'] = $args[0];
                            $p['class'] = get_class($args[0]);
                            $p['function'] = $args[1];
                            $p['args'] = $args[2];
                            $p['type'] = '->';
                            $unset_indices[] = $i - 1; // reflection adds another line in trace that is not needed
                        } else if ($p['function'] === 'callStaticMethod') {
                            $p['class'] = $args[0];
                            $p['function'] = $args[1];
                            $p['args'] = $args[2];
                            $p['type'] = '::';
                            $unset_indices[] = $i - 1; // reflection adds another line in trace that is not needed
                        }
                    }

                    if ($ignore_args) {
                        unset($p['args']);
                    }

                    if (!$provide_object) {
                        unset($p['object']);
                    }
                }

                foreach ($unset_indices as $i) {
                    unset($result[$i]);
                }

                $result = array_values($result);

                if (!$limit) {
                    return $result;
                }

                $limited_result = [];
                for ($i = 0; $i < $limit; $i++) {
                    $limited_result[] = $result[$i];
                }

                return $limited_result;
            },
        ];

        self::$internal_func_mocks = [];
        foreach (self::$func_mocks as $func => $mock) {
            self::$internal_func_mocks[$func] = $mock;
            self::$internal_func_mocks_by_name[$func][$func] = true;
        }
        self::$func_mocks_by_name = self::$internal_func_mocks_by_name;

        $functions = get_defined_functions();
        foreach ($functions['internal'] as $func) {
            self::$internal_functions[$func] = true;
        }

        self::ignoreFiles(get_included_files());
        self::injectIntoPhpunit();
        self::initProjectPath();
        self::initParserVersion();
    }

    public static function setVendorPath(string $vendor_path)
    {
        self::$vendor_path = $vendor_path;
    }

    /**
     * Parser version is needed for create new files when parser version changed 
     */
    protected static function initParserVersion()
    {
        if (!self::$vendor_path) {
            throw new \RuntimeException(
                'Vendor path is required for get nikic/php-parser version for for right rewritten files cache'
            );
        }
        $installed_file = self::$vendor_path . '/composer/installed.json';
        $installed_content = file_get_contents($installed_file);
        if (!$installed_content) {
            throw new \RuntimeException(
                "Can't get installed packages for get nikic/php-parser version for for right rewritten files cache"
            );
        }
        /** @noinspection PhpComposerExtensionStubsInspection */
        $installed = json_decode($installed_content, true);
        /** @noinspection PhpComposerExtensionStubsInspection */
        if (json_last_error() !== \JSON_ERROR_NONE) {
            $error = '[' . json_last_error() . '] ' . json_last_error_msg();
            throw new \RuntimeException(
                "Can't parse installed packages json for get nikic/php-parser version for for right rewritten files cache: {$error}"
            );
        }
        
        // If the installed.json is the latest version then update the installed variable
        if (array_key_exists('packages', $installed)) {
            $installed = $installed['packages'];
        }
        foreach ($installed as $package) {
            if (!isset($package['name'], $package['version'])) {
                continue;
            }
            if ($package['name'] !== 'nikic/php-parser') {
                continue;
            }
            self::$parser_version = $package['version'];
            return;
        }
        throw new \RuntimeException(
            "Can't find nikic/php-parser version in installed packages for right rewritten files cache"
        );
    }

    protected static function initProjectPath()
    {
        $lib_path = dirname(dirname(__DIR__));
        $vendor_path = dirname(dirname($lib_path));
        if (basename($vendor_path) === 'vendor') {
            self::$project_path = dirname($vendor_path);
            return;
        }
        self::$project_path = $lib_path;
    }

    public static function setProjectPath($project_path)
    {
        if (!empty($project_path)) {
            self::$project_path = rtrim($project_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if (!is_dir(self::$project_path)) {
            throw new \RuntimeException("Project path isn't exists");
        }
    }

    /**
     * @param bool $rewrite_internal
     */
    public static function setRewriteInternal($rewrite_internal)
    {
        self::$rewrite_internal = (bool)$rewrite_internal;
    }

    /**
     * @param string $mocks_cache_path - Path to cache of rewritten files
     * @throws \RuntimeException
     */
    public static function setMocksCachePath($mocks_cache_path)
    {
        if (!empty($mocks_cache_path)) {
            self::$mocks_cache_path = rtrim($mocks_cache_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if (!is_dir(self::$mocks_cache_path) && !mkdir(self::$mocks_cache_path, 0777) && !is_dir(self::$mocks_cache_path)) {
            throw new \RuntimeException("Can't create cache dir for rewritten files at " . self::$mocks_cache_path);
        }
    }

    /**
     * @param $lock_file_path - Path to lock file that is used when file is rewritten
     */
    public static function setLockFilePath($lock_file_path)
    {
        if (!empty($lock_file_path)) {
            self::$lock_file_path = $lock_file_path;
        }

        if (!file_exists(self::$lock_file_path) && !touch(self::$lock_file_path)) {
            throw new \RuntimeException("Can't create lock file at " . self::$lock_file_path);
        }
    }

    /**
     * @deprecated use addIgnorePath
     * @see addIgnoreSubPath
     * @param string $phpunit_path - Part of path to phpunit so that it can be ignored when rewriting files
     */
    public static function setPhpunitPath($phpunit_path)
    {
        if ($phpunit_path) {
            unset(self::$ignore_sub_paths['/phpunit/']);
        }
        self::addIgnoreSubPath($phpunit_path);
    }

    /**
     * @deprecated use addIgnorePath
     * @see addIgnoreSubPath
     * @param $php_parser_path - Part of path to PHP Parser so that it can be ignored when rewriting files
     */
    public static function setPhpParserPath($php_parser_path)
    {
        if ($php_parser_path) {
            unset(self::$ignore_sub_paths['/php-parser/']);
        }
        self::addIgnoreSubPath($php_parser_path);
    }

    /**
     * @param string $sub_path Part of path so that it can be ignored when rewriting files
     */
    public static function addIgnoreSubPath($sub_path)
    {
        if (!empty($sub_path)) {
            self::$ignore_sub_paths[$sub_path] = $sub_path;
        }
    }

    /**
     * @param array $ignore_sub_paths will be ignored when rewriting files:
     * array(
     *     'path' => 'path',
     * )
     */
    public static function setIgnoreSubPaths(array $ignore_sub_paths)
    {
        self::$ignore_sub_paths = $ignore_sub_paths;
    }

    public static function addBasePath($base_path)
    {
        if (!empty($base_path)) {
            self::$base_paths[] = $base_path;
        }
    }

    public static function setBasePaths(array $base_paths)
    {
        self::$base_paths = $base_paths;
    }

    /**
     * @param callable $prepare_for_rewrite_callback
     */
    public static function setPrepareForRewriteCallback($prepare_for_rewrite_callback)
    {
        if (!empty($prepare_for_rewrite_callback)) {
            self::$prepare_for_rewrite_callback = $prepare_for_rewrite_callback;
        }
    }

    /**
     * @param string $class - Do not allow to mock $class
     */
    public static function ignoreClass($class)
    {
        SoftMocksTraverser::ignoreClass(ltrim($class, '\\'));
    }

    /**
     * @param string $constant - Do not allow to mock $constant
     */
    public static function ignoreConstant($constant)
    {
        SoftMocksTraverser::ignoreConstant(ltrim($constant, '\\'));
    }

    /**
     * @param string $function - Do not allow to mock $function
     */
    public static function ignoreFunction($function)
    {
        SoftMocksTraverser::ignoreFunction(ltrim($function, '\\'));
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return;
        }
        $descr = isset(self::$error_descriptions[$errno]) ? self::$error_descriptions[$errno] : "Unknown error ($errno)";
        echo "\n$descr: $errstr in " . self::replaceFilename($errfile) . " on line $errline\n";
    }

    public static function printBackTrace($e = null)
    {
        $str = $e ?: new \Exception();
        if ($str instanceof \PHPUnit_Framework_ExceptionWrapper || $str instanceof \PHPUnit\Framework\ExceptionWrapper) {
            $trace = [];
            foreach ($str->getSerializableTrace() as $idx => $frame) {
                $frame += ['file' => '', 'line' => '', 'class' => '', 'type' => '', 'function' => ''];
                $trace[] = sprintf('#%d %s(%s): %s%s%s()', $idx, $frame['file'], $frame['line'], $frame['class'], $frame['type'], $frame['function']);
            }
            $trace_str = implode("\n", $trace);
        } else {
            $trace_str = $str->getTraceAsString();
        }
        $trace_str = $str->getFile() . '(' . $str->getLine() . ")\n$trace_str";

        if (!empty(static::getEnvironment('REAL_BACKTRACE'))) {
            echo $trace_str;
            return;
        }
        $trace_lines = explode("\n", $trace_str);

        foreach ($trace_lines as &$ln) {
            $ln = preg_replace_callback(
                '#(/[^:(]+)([:(])#',
                function ($data) {
                    return self::clearBasePath(self::replaceFilename($data[1], true) . $data[2]);
                },
                $ln
            );

            $ln = str_replace('[internal function]', '', $ln);
            $ln = preg_replace('/^\\#\\d+\\s*\\:?\\s*/s', '', $ln);
        }

        echo "(use REAL_BACKTRACE=1 to get real trace) " . implode(
            "\n ",
            array_filter(
                $trace_lines,
                function ($str) {
                    $parts = explode('(', trim($str), 2);
                    if (count($parts) > 1) {
                        $filename = $parts[0];
                        if (stripos($filename, 'PHPUnit') !== false) {
                            return false;
                        }
                    }

                    foreach (self::$backtrace_ignored_method_calls as list($ignoreClass, $ignoreMethod)) {
                        if (
                            strpos($str, " {$ignoreClass}->{$ignoreMethod}(") !== false ||
                            strpos($str, " {$ignoreClass}::{$ignoreMethod}(") !== false
                        ) {
                            return false;
                        }
                    }

                    return strpos($str, 'PHPUnit_Framework_ExpectationFailedException') === false
                        && strpos($str, '{main}') === false
                        && strpos($str, basename(__FILE__)) === false;
                }
            )
        ) . "\n";
    }

    public static function backtraceIgnoreMethodCall($class, $methodName)
    {
        self::$backtrace_ignored_method_calls[] = [$class, $methodName];
    }

    public static function replaceFilenameRaw($file)
    {
        return self::replaceFilename($file, true);
    }

    public static function replaceFilename($file, $raw = false)
    {
        if (isset(self::$orig_paths[$file])) {
            if ($raw) {
                return self::$orig_paths[$file];
            }
            return $file . " (orig: " . self::$orig_paths[$file] . ")";
        }

        return $file;
    }

    public static function clearBasePath($file)
    {
        foreach (self::$base_paths as $base_path) {
            if (strpos($file, $base_path) === 0) {
                return substr($file, strlen($base_path));
            }
        }
        return $file;
    }

    private static function getMocksDirVersion()
    {
        if (!isset(self::$mocks_dir_version)) {
            // Previously:
            // phpversion() . self::$parser_version . md5_file(__FILE__);

            $parts = [];

            $parts[] = phpversion();

            if (defined(PHP_BINARY)) {
                $parts[] = substr(md5_file(PHP_BINARY), 0, 7);
            }

            $parts[] = substr(md5(new \ReflectionExtension("tokenizer")), 0, 7);

            $parts[] = self::$parser_version;

            $parts[] = substr(md5_file(__FILE__), 0, 7);

            self::$mocks_dir_version = implode('-', $parts);
        }
        return self::$mocks_dir_version;
    }

    /**
     * @param string $file
     * @return string
     * @throws \RuntimeException if can't rewrite
     */
    public static function rewrite($file)
    {
        $file = self::prepareFilePathToRewrite($file);

        if (!self::shouldRewriteFile($file)) {
            return $file;
        }

        if (!isset(self::$rewrite_cache[$file])) {
            $md5_file = md5_file($file);
            if (!$md5_file) {
                return (self::$orig_paths[$file] = self::$rewrite_cache[$file] = $file);
            }

            $target_file = self::constructRewrittenFilePath($file, $md5_file);
            if (!file_exists($target_file)) {
                $old_umask = umask(0);
                self::createRewrittenFile($file, $target_file);
                umask($old_umask);
                /* simulate atime to prevent deletion files if you use find "$CACHE_DIR" -mtime +14 -type f -delete */
            } else if (time() - filemtime($target_file) > self::MOCKS_CACHE_TOUCHTIME) {
                touch($target_file);
            }

            $real_target_file = realpath($target_file);
            if (!$real_target_file) {
                throw new \RuntimeException("Can't resolve rewritten file '{$target_file}' for file '{$file}'");
            }
            self::$rewrite_cache[$file] = $real_target_file;
            self::$orig_paths[$real_target_file] = $file;
        }

        return self::$rewrite_cache[$file];
    }

    /**
     * @param string $file
     * @return string
     * @throws \RuntimeException
     */
    private static function prepareFilePathToRewrite($file)
    {
        if (self::$prepare_for_rewrite_callback !== null) {
            $callback = self::$prepare_for_rewrite_callback;
            $file = $callback($file);
        }
        return self::resolveFile($file);
    }

    private static function shouldRewriteFile($file)
    {
        if (
            strpos($file, self::$mocks_cache_path) === 0 ||
            strpos($file, self::getMocksDirVersion() . DIRECTORY_SEPARATOR) === 0
        ) {
            return false;
        }

        foreach (self::$ignore_sub_paths as $ignore_path) {
            if (strpos($file, $ignore_path) !== false) {
                return false;
            }
        }

        if (isset(self::$ignore[$file])) {
            return false;
        }
        return true;
    }

    private static function constructRewrittenFilePath($file, $md5_file)
    {
        $clean_filepath = self::getCleanFilePath($file);

        if (self::$debug) {
            self::debug("Clean filepath for $file is $clean_filepath");
        }

        $md5 = self::getMd5ForSuffix($clean_filepath, $md5_file);
        if (self::$project_path && strpos($file, self::$project_path) === 0) {
            $file_in_project = substr($file, strlen(self::$project_path));
        } else {
            $file_in_project = $file;
        }
        $file_in_project = ltrim($file_in_project, DIRECTORY_SEPARATOR);

        return self::getRewrittentFilePathPrefix() . DIRECTORY_SEPARATOR . "{$file_in_project}_{$md5}.php";
    }

    private static function getCleanFilePath($file)
    {
        if (strpos($file, SOFTMOCKS_ROOT_PATH) !== 0) {
            return $file;
        }
        return substr($file, strlen(SOFTMOCKS_ROOT_PATH));
    }

    private static function getMd5ForSuffix($clean_filepath, $md5_file)
    {
        return md5($clean_filepath . ':' . $md5_file);
    }

    /**
     * This is not public API actually, supposed to be used for IDE integrations.
     *
     * @internal
     *
     * @return string
     */
    public static function getRewrittentFilePathPrefix()
    {
        return self::$mocks_cache_path . self::getMocksDirVersion();
    }

    /**
     * @param $file
     * @return string
     * @throws \RuntimeException
     */
    public static function getRewrittenFilePath($file)
    {
        $file = self::prepareFilePathToRewrite($file);

        if (!self::shouldRewriteFile($file)) {
            return $file;
        }
        $md5_file = md5_file($file);
        if (!$md5_file) {
            return $file;
        }

        return self::constructRewrittenFilePath($file, $md5_file);
    }

    public static function getOriginalFilePath($file)
    {
        $rewritten_prefix = self::getRewrittentFilePathPrefix();
        $pattern = '#^' . preg_quote($rewritten_prefix . DIRECTORY_SEPARATOR, '#')
            . '(?P<path>.+)_(?P<md5>[0-9a-f]{32})\.php$#';
        if (!preg_match($pattern, $file, $matches)) {
            return $file;
        }
        $path = DIRECTORY_SEPARATOR . $matches['path'];
        $expected_md5 = $matches['md5'];
        $possible_path_prefixes = [''];
        if (self::$project_path) {
            $possible_path_prefixes[] = self::$project_path;
        }
        $root_path = rtrim(SOFTMOCKS_ROOT_PATH, DIRECTORY_SEPARATOR);
        if ($root_path) {
            $possible_path_prefixes[] = $root_path;
            if (self::$project_path) {
                $possible_path_prefixes[] = $root_path . self::$project_path;
            }
        }

        foreach ($possible_path_prefixes as $possible_path_prefix) {
            $original_file = $possible_path_prefix . $path;
            if (!file_exists($original_file)) {
                continue;
            }
            $md5_file = md5_file($original_file);
            if (!$md5_file) {
                continue;
            }
            $clean_file_path = self::getCleanFilePath($original_file);
            $md5 = self::getMd5ForSuffix($clean_file_path, $md5_file);
            if ($expected_md5 !== $md5) {
                continue;
            }
            return $original_file;
        }
        return $file;
    }

    /**
     * @param $file
     * @return string
     * @throws \RuntimeException
     */
    private static function resolveFile($file)
    {
        if (!$file) {
            throw new \RuntimeException('File should not be empty');
        }
        // if path is not absolute
        if ($file[0] !== '/') {
            // skip stream
            $path_info = parse_url($file);
            if (isset($path_info['scheme'])) {
                return $file;
            }
            $found = false;
            $cwd = getcwd();
            // try include path
            foreach (explode(':', get_include_path()) as $dir) {
                if ($dir === '.') {
                    $dir = $cwd;
                }

                if (file_exists("{$dir}/{$file}")) {
                    $file = "{$dir}/{$file}";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // try relative path
                $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                if (isset($bt[2]['file'])) {
                    $dir = dirname(self::replaceFilename($bt[2]['file'], true));
                    if (file_exists("{$dir}/{$file}")) {
                        $file = "{$dir}/{$file}";
                    } else {
                        // try cwd
                        $dir = $cwd;
                        if (file_exists("{$dir}/{$file}")) {
                            $file = "{$dir}/{$file}";
                        }
                    }
                }
            }
        }
        // resolve symlinks
        $real_file = realpath($file);
        if (!$real_file) {
            throw new \RuntimeException("Can't resolve file '{$file}'");
        }
        return $real_file;
    }

    /**
     * @param string $file
     * @param string $target_file
     * @throws \RuntimeException
     */
    private static function createRewrittenFile($file, $target_file)
    {
        if (self::$debug) {
            echo "Rewriting $file => $target_file\n";
            echo new \Exception();
            while (ob_get_level()) ob_end_flush();
        }

        $contents = file_get_contents($file);
        $old_nesting_level = ini_set('xdebug.max_nesting_level', 3000);
        $contents = self::rewriteContents($file, $target_file, $contents);
        ini_set('xdebug.max_nesting_level', $old_nesting_level);

        if (!$fp = fopen(self::$lock_file_path, 'a+')) {
            throw new \RuntimeException("Could not create lock file " . self::$lock_file_path);
        }

        if (!flock($fp, LOCK_EX)) {
            throw new \RuntimeException("Could not flock " . self::$lock_file_path);
        }

        $target_dir = dirname($target_file);
        $base_mocks_path = '';
        $relative_target_dir = $target_dir;
        if (strpos($file, self::$mocks_cache_path) === 0) {
            $base_mocks_path = self::$mocks_cache_path;
            $relative_target_dir = substr($target_dir, strlen($base_mocks_path));
        }
        self::createDirRecursive($base_mocks_path, $relative_target_dir);

        $tmp_file = $target_file . ".tmp." . uniqid(getmypid());
        $wrote = file_put_contents($tmp_file, $contents);
        $expected_bytes = strlen($contents);
        if ($wrote !== $expected_bytes) {
            throw new \RuntimeException('Could not fully write rewritten content! Wrote ' . var_export($wrote, true) . " instead of $expected_bytes");
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            // You cannot atomically replace files in Windows
            if (file_exists($target_file) && !unlink($target_file)) {
                throw new \RuntimeException("Could not unlink $target_file");
            }
        }

        if (!rename($tmp_file, $target_file)) {
            throw new \RuntimeException("Could not move tmp rewritten file $tmp_file into $target_file");
        }

        if (!fclose($fp)) {
            throw new \RuntimeException("Could not fclose lock file descriptor for file " . self::$lock_file_path);
        }
    }

    /**
     * Create dir recursive
     * if create dirs recursive using mkdir with $recursive = true, then there can be race conditions, for example:
     * process1: mkdir('/foo/bar1', 0777, true);
     * process1: check '/foo', it not exists, try to create it
     * process2: mkdir('/foo/bar2', 0777, true);
     * process2: check '/foo', it not exists, try to create it
     * process1: successful created '/foo', check '/foo/bar1', it not exists, create it
     * process1: can't create '/foo', '/foo/bar2' not exists, fail
     * to prevent this race condition, create dir recursive manually
     *
     * @see https://bugs.php.net/bug.php?id=35326
     *
     * @param string $base_dir base (existing) dir
     * @param string $relative_target_dir dir, which need to create
     * @throws \RuntimeException
     */
    private static function createDirRecursive($base_dir, $relative_target_dir)
    {
        $current_dir = $base_dir;
        foreach (explode(DIRECTORY_SEPARATOR, $relative_target_dir) as $sub_dir) {
            $current_dir .= DIRECTORY_SEPARATOR . $sub_dir;
            if (!is_dir($current_dir) && !mkdir($current_dir, 0777) && !is_dir($current_dir)) {
                $error = error_get_last();
                $message = '';
                if (is_array($error)) {
                    $message = ", error: {$error['message']}";
                }
                throw new \RuntimeException("Can't create directory {$current_dir}{$message}");
            }
        }
    }

    /**
     * Generic method to call a callable, useful for proxying call_user_func* calls
     *
     * @param $callable
     * @param $args
     * @return mixed
     */
    public static function call($ns, $callable, $args)
    {
        if (is_scalar($callable) && strpos($callable, '::') === false) {
            return self::callFunction($ns, $callable, $args);
        }

        if (is_scalar($callable)) {
            $parts = explode('::', $callable);
            if (count($parts) != 2) {
                throw new \RuntimeException("Invalid callable format for '$callable', expected single '::'");
            }
            list($obj, $method) = $parts;
        } else if (is_array($callable)) {
            if (count($callable) != 2) {
                throw new \RuntimeException("Invalid callable format, expected array of exactly 2 elements");
            }
            list($obj, $method) = $callable;
        } else {
            return call_user_func_array($callable, $args);
        }

        if (is_object($obj)) {
            return self::callMethod($obj, null, $method, $args, true);
        } else if (is_scalar($obj)) {
            return self::callStaticMethod($obj, $method, $args, true);
        }

        throw new \RuntimeException("Invalid callable format, expected first array element to be object or scalar, " . gettype($obj) . " given");
    }

    public static function isCallable($callable)
    {
        if (empty($callable)) {
            return false;
        }

        if (is_array($callable) && sizeof($callable) === 2) {
            if (!isset($callable[0]) || !isset($callable[1])) {
                return is_callable($callable);
            }

            if (is_object($callable[0])) {
                $class = get_class($callable[0]);
            } else {
                $class = $callable[0];
            }

            if (isset(self::$mocks[$class][$callable[1]])) {
                return true;
            }

            return is_callable($callable);
        }

        if (is_string($callable) && isset(self::$func_mocks[$callable])) {
            return true;
        }

        return is_callable($callable);
    }

    public static function constDefined($const)
    {
        if (isset(self::$removed_constants[$const])) {
            return false;
        }
        return defined($const) || isset(self::$constant_mocks[$const]);
    }

    public static function callMethod($obj, $class, $method, $args, $check_mock = false)
    {
        if (!$class) {
            $class = get_class($obj);
        }
        if ($check_mock && isset(self::$mocks[$class][$method])) {
            if (self::$debug) {
                self::debug("Intercepting call to $class->$method");
            }
            return SoftMocksFunctionCreator::run($obj, $class, $args, self::$mocks[$class][$method]);
        }

        try {
            $Rm = new \ReflectionMethod($class, $method);
            $Rm->setAccessible(true);

            $decl_class = $Rm->getDeclaringClass()->getName();
            if ($check_mock && isset(self::$mocks[$decl_class][$method])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $class->$method");
                }
                return SoftMocksFunctionCreator::run($obj, $class, $args, self::$mocks[$decl_class][$method]);
            }
        } catch (\ReflectionException $e) {
            if (\method_exists($e, 'getPrevious') && $e->getPrevious()) {
                // If there's an underlying error (e.g. an exception thrown during autoloading) - we need to pass it
                throw $e->getPrevious();
            }

            if (method_exists($obj, '__call')) {
                $Rm = new \ReflectionMethod($obj, '__call');
                $Rm->setAccessible(true);
                return $Rm->invokeArgs($obj, [$method, $args]);
            }

            return call_user_func_array([$obj, $method], $args); // give up, got some weird shit
        }

        return $Rm->invokeArgs($obj, $args);
    }

    public static function callStaticMethod($class, $method, $args, $check_mock = false)
    {
        if ($check_mock && isset(self::$mocks[$class][$method])) {
            if (self::$debug) {
                self::debug("Intercepting call to $class::$method");
            }
            return SoftMocksFunctionCreator::run(null, $class, $args, self::$mocks[$class][$method]);
        }

        try {
            $Rm = new \ReflectionMethod($class, $method);
            $Rm->setAccessible(true);

            $decl_class = $Rm->getDeclaringClass()->getName();

            if ($check_mock && isset(self::$mocks[$decl_class][$method])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $class::$method");
                }
                return SoftMocksFunctionCreator::run(null, $class, $args, self::$mocks[$decl_class][$method]);
            }
        } catch (\ReflectionException $e) {
            if (\method_exists($e, 'getPrevious') && $e->getPrevious()) {
                // If there's an underlying error (e.g. an exception thrown during autoloading) - we need to pass it
                throw $e->getPrevious();
            }

            if (method_exists($class, '__callStatic')) {
                $Rm = new \ReflectionMethod($class, '__callStatic');
                $Rm->setAccessible(true);
                return $Rm->invokeArgs(null, [$method, $args]);
            }

            return call_user_func_array([$class, $method], $args);
        }

        return $Rm->invokeArgs(null, $args);
    }

    public static function callExit($code = '')
    {
        if (empty(self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT])) {
            exit($code);
        } else {
            if (self::$debug) {
                self::debug("Intercepting call to exit()/die()");
            }
            $params = [$code]; // $params will be used inside the eval()
            if (self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['code'] instanceof \Closure) {
                $callable = self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['code'];
            } else {
                $callable = eval("return function(" . self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['args'] . ") use(\$params) { " . self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]['code'] . " };");
            }
            return call_user_func($callable, $code);
        }
    }

    public static function callFunction($namespace, $func, $params)
    {
        if ($namespace !== '' && is_scalar($func)) {
            $ns_func = $namespace . '\\' . $func;
            if (isset(self::$func_mocks[$ns_func])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $ns_func");
                }
                if (self::$func_mocks[$ns_func]['code'] instanceof \Closure) {
                    $func_callable = self::$func_mocks[$ns_func]['code'];
                } else {
                    $func_callable = eval("return function(" . self::$func_mocks[$ns_func]['args'] . ") use (\$params) { " . self::$func_mocks[$ns_func]['code'] . " };");
                }

                return call_user_func_array($func_callable, $params);
            }

            if (is_callable($ns_func)) {
                return call_user_func_array($ns_func, $params);
            }
        }
        if (is_scalar($func)) {
            if (isset(self::$func_mocks[$func])) {
                if (self::$debug) {
                    self::debug("Intercepting call to $func");
                }
                if (self::$func_mocks[$func]['code'] instanceof \Closure) {
                    $func_callable = self::$func_mocks[$func]['code'];
                } else {
                    $func_callable = eval("return function(" . self::$func_mocks[$func]['args'] . ") use (\$params) { " . self::$func_mocks[$func]['code'] . " };");
                }
                return call_user_func_array($func_callable, $params);
            }
        }
        return call_user_func_array($func, $params);
    }

    public static function callNew($class, $args)
    {
        if (isset(self::$new_mocks[$class])) {
            return call_user_func_array(self::$new_mocks[$class], $args);
        }

        $Rc = new \ReflectionClass($class);
        $Constructor = $Rc->getConstructor();

        if ($Constructor && !$Constructor->isPublic()) {
            $instance = $Rc->newInstanceWithoutConstructor();
            $Constructor->setAccessible(true);
            $Constructor->invokeArgs($instance, $args);
        } else {
            $instance = $Rc->newInstanceArgs($args);
        }

        return $instance;
    }

    public static function getConst($namespace, $const)
    {
        if ($namespace !== '') {
            $ns_const = $namespace . '\\' . $const;
            if (array_key_exists($ns_const, self::$constant_mocks)) {
                if (self::$debug) {
                    self::debug("Mocked $ns_const");
                }
                return self::$constant_mocks[$ns_const];
            }

            if (defined($ns_const)) {
                return constant($ns_const);
            }
        }

        if (array_key_exists($const, self::$constant_mocks)) {
            if (self::$debug) {
                self::debug("Mocked $const");
            }
            return self::$constant_mocks[$const];
        }

        if (array_key_exists($const, self::$removed_constants)) {
            trigger_error('Trying to access removed constant ' . $const . ', assuming "' . $const . '"');
            return $const;
        }

        return constant($const);
    }

    public static function getClassConst($class, $const, $self_class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $const_full_name = "{$class}::{$const}";

        if (PHP_VERSION_ID < 70100) {
            // for php versions < 7.1 where isn't declared class ReflectionClassConstant
            $ancestor = $class;
            do {
                $ancestor_const = "{$ancestor}::{$const}";
                if (isset(self::$removed_constants[$ancestor_const])) {
                    $parent = \get_parent_class($ancestor);
                    continue;
                }
                if (isset(self::$constant_mocks[$ancestor_const])) {
                    if (self::$debug) {
                        self::debug("Intercepting constant {$ancestor_const}");
                    }
                    return self::$constant_mocks[$ancestor_const];
                }
                $ancestor_const_defined = defined($ancestor_const);
                if (!$ancestor_const_defined) {
                    break;
                }
                // for php versions < 7.1
                // we can't get constant declaring class, but we can compare values for $ancestor and parent class
                $parent = get_parent_class($ancestor);
                if ($parent) {
                    $parent_const = "{$parent}::{$const}";
                    $parent_const_defined = defined($parent_const);
                    if (!$parent_const_defined) {
                        break;
                    }
                    if (constant($ancestor_const) !== constant($parent_const)) {
                        break;
                    }
                }
            } while ($ancestor = $parent);

            if (!defined($ancestor_const)) {
                throw new \RuntimeException("Undefined class constant '{$const_full_name}'");
            }
            return constant($ancestor_const);
        }

        // for php versions >= 7.1
        $ConstantReflection = null;
        $declaring_class = null;
        $ancestor = $class;
        do {
            $ancestor_const = "{$ancestor}::{$const}";
            if (isset(self::$removed_constants[$ancestor_const])) {
                if ($declaring_class === $ancestor) {
                    // for get next declaring class
                    $ConstantReflection = null;
                    $declaring_class = null;
                }
                continue;
            }
            if ($declaring_class === null) {
                try {
                    $ConstantReflection = new \ReflectionClassConstant($ancestor, $const);
                    if ($ConstantReflection->isPrivate()) {
                        if ($self_class === null || ($self_class !== $class)) {
                            throw new \Error("Cannot access private const {$const_full_name}");
                        }
                    }
                    if ($ConstantReflection->isProtected()) {
                        if ($self_class === null || (($self_class !== $class) && !is_subclass_of($class, $self_class) && !is_subclass_of($self_class, $class))) {
                            throw new \Error("Cannot access protected const {$const_full_name}");
                        }
                    }
                    $declaring_class = $ConstantReflection->getDeclaringClass()->getName();
                } catch (\ReflectionException $Exception) {
                    if (\method_exists($Exception, 'getPrevious') && $Exception->getPrevious()) {
                        // If there's an underlying error (e.g. an exception thrown during autoloading) - we need to pass it
                        throw $Exception->getPrevious();
                    } else {/* if we add new constant */}
                }
                if (!$declaring_class) {
                    $declaring_class = false;
                }
            }
            if (isset(self::$constant_mocks[$ancestor_const])) {
                if (self::$debug) {
                    self::debug("Intercepting constant {$ancestor_const}");
                }
                return self::$constant_mocks[$ancestor_const];
            }
            if ($declaring_class && $declaring_class === $ancestor) {
                break;
            }
        } while ($ancestor = get_parent_class($ancestor));
        if (!$ConstantReflection) {
            throw new \Error("Undefined class constant '{$const_full_name}'");
        }
        // To avoid 'Cannot access private/protected const' error, see above
        return $ConstantReflection->getValue();
    }

    private static function rewriteContents($orig_file, $target_file, $contents)
    {
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
        $traverser->addVisitor(new SoftMocksTraverser($orig_file));

        $prettyPrinter = new SoftMocksPrinter();
        $errorHandler = new SoftMocksParseErrorHandler($orig_file);

        $parser = (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::ONLY_PHP7, new \PhpParser\Lexer());
        $stmts = $parser->parse($contents, $errorHandler);
        $stmts = $traverser->traverse($stmts);

        return $prettyPrinter->prettyPrintFile($stmts);
    }

    public static function ignoreFiles($files)
    {
        foreach ($files as $f) {
            if (self::$debug) {
                self::debug("Asked to ignore $f");
            }
            self::$ignore[$f] = true;
        }
    }

    public static function redefineFunction($func, $functionArgs, $fakeCode)
    {
        if (self::$debug) {
            self::debug("Asked to redefine $func($functionArgs)");
        }
        if (!self::$rewrite_internal && isset(self::$internal_func_mocks[$func])) {
            throw new \RuntimeException("Function $func is mocked internally, cannot mock");
        }
        if (SoftMocksTraverser::isFunctionIgnored($func)) {
            throw new \RuntimeException("Function $func cannot be mocked using Soft Mocks");
        }
        $mock_array = ['args' => $functionArgs, 'code' => $fakeCode];
        if (self::$paused) self::$paused_func_mocks[$func] = $mock_array;
        else self::$func_mocks[$func] = $mock_array;
        $funcBaseName = array_slice(explode("\\", $func), -1)[0];
        self::$func_mocks_by_name[$funcBaseName][$func] = true;
    }

    public static function redefineExit($args, $fakeCode)
    {
        if (self::$debug) {
            self::debug("Asked to redefine exit(\$code)");
        }
        $mock_array = ['args' => $args, 'code' => $fakeCode];
        if (self::$paused) self::$paused_lang_construct_mocks[self::LANG_CONSTRUCT_EXIT] = $mock_array;
        else self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT] = $mock_array;
    }

    public static function restoreFunction($func)
    {
        if (isset(self::$internal_func_mocks[$func])) {
            if (!self::$rewrite_internal) {
                throw new \RuntimeException("Function $func is mocked internally, cannot unmock");
            }
            self::$func_mocks[$func] = self::$internal_func_mocks[$func];
            return;
        }

        unset(self::$func_mocks[$func]);
        $funcBaseName = array_slice(explode("\\", $func), -1)[0];
        unset(self::$func_mocks_by_name[$funcBaseName][$func]);
        if ((self::$func_mocks_by_name[$funcBaseName] ?? null) === []) {
            unset(self::$func_mocks_by_name[$funcBaseName]);
        }
    }

    public static function restoreAll()
    {
        self::restoreAllActual();
        self::restoreAllPaused();
        self::$paused = false;
        self::$mocks_by_name = [];
        self::$class_const_mocks_by_name = [];
        self::$func_mocks_by_name = self::$internal_func_mocks_by_name;
    }

    private static function restoreAllActual()
    {
        self::$mocks = [];
        self::$generator_mocks = [];
        self::$func_mocks = self::$internal_func_mocks;
        self::$temp_disable = false;
        self::$lang_construct_mocks = [];
        self::restoreAllConstantsActual();
        self::restoreAllNewActual();
    }

    private static function restoreAllPaused()
    {
        self::$paused_mocks = [];
        self::$paused_generator_mocks = [];
        self::$paused_func_mocks = [];
        self::$paused_temp_disable = false;
        self::$paused_lang_construct_mocks = [];
        self::restoreAllConstantsPaused();
        self::restoreAllNewPaused();
    }

    /**
     * pause all SoftMocks
     */
    public static function pause()
    {
        if (self::$paused === false) {
            self::$paused_mocks = self::$mocks;
            self::$paused_generator_mocks = self::$generator_mocks;
            self::$paused_func_mocks = self::$func_mocks;
            self::$paused_temp_disable = self::$temp_disable;
            self::$paused_lang_construct_mocks = self::$lang_construct_mocks;
            self::$paused_constant_mocks = self::$constant_mocks;
            self::$paused_removed_constants = self::$removed_constants;
            self::$paused_new_mocks = self::$new_mocks;
            self::restoreAllActual();

            self::$paused = true;
        }
    }

    /**
     * revert pause function
     * @see SoftMocks::pause()
     */
    public static function resume()
    {
        if (self::$paused === true) {
            self::$mocks = self::$paused_mocks;
            self::$generator_mocks = self::$paused_generator_mocks;
            self::$func_mocks = self::$paused_func_mocks;
            self::$temp_disable = self::$paused_temp_disable;
            self::$lang_construct_mocks = self::$paused_lang_construct_mocks;
            self::$constant_mocks = self::$paused_constant_mocks;
            self::$removed_constants = self::$paused_removed_constants;
            self::$new_mocks = self::$paused_new_mocks;
            self::restoreAllPaused();

            self::$paused = false;
        }
    }

    /**
     * Redefine method $class::$method with args list specified by $functionArgs
     * (args list must be compatible with original function, you can specify only variable names)
     * by using code $fakeCode instead of original function code.
     *
     * There are two already defined variables that you can use in fake code:
     *  $mm_func_args = func_get_args();
     *  $params is array of references to supplied arguments (func_get_args() does not contain refs in PHP5)
     *
     * You can use SoftMocks::callOriginal(...) for accessing original function/method as well
     *
     * Example:
     *
     *  class A { public function b($c, &$d) { var_dump($c, $d); } }
     *
     *  SoftMocks::redefineMethod(A::class, 'b', '$e, &$f', '$f = "hello";');
     *  $a = 2;
     *  (new A())->b(1, $a); // nothing is printed here, so we intercepted the call
     *  var_dump($a); // string(5) "hello"
     *
     * @param string $class
     * @param string $method         Method of class to be intercepted
     * @param string $functionArgs   List of argument names
     * @param string $fakeCode       Code that will be eval'ed instead of function code
     */
    public static function redefineMethod($class, $method, $functionArgs, $fakeCode)
    {
        if (self::$debug) {
            self::debug("Asked to redefine $class::$method($functionArgs)");
        }
        if (SoftMocksTraverser::isClassIgnored($class)) {
            throw new \RuntimeException("Class $class cannot be mocked using Soft Mocks");
        }

        $params = [];
        $real_classname = $real_methodname = '';
        try {
            $Rc = new \ReflectionClass($class);
            $real_classname = $Rc->getName();
            $Rm = $Rc->getMethod($method);
            $real_methodname = $Rm->getName();
            $params = $Rm->getParameters();
        } catch (\Exception $e) {
            if (self::$debug) {
                self::debug("Could not get parameters for $class::$method via reflection: $e");
            }
        }

        if (($real_classname && $real_classname != $class) || ($real_methodname && $real_methodname != $method)) {
            throw new \RuntimeException("Requested to mock $class::$method while method name is $real_classname::$real_methodname");
        }
        $mock_array = [
            'args' => $functionArgs,
            'code' => self::generateCode($functionArgs, $params) . $fakeCode,
        ];
        if (self::$paused) self::$paused_mocks[$class][$method] = $mock_array;
        else self::$mocks[$class][$method] = $mock_array;
        self::$mocks_by_name[$method][$class] = true;
    }

    public static function restoreMethod($class, $method)
    {
        if (self::$debug) {
            self::debug("Restore method $class::$method");
        }
        if (isset(self::$mocks[$class][$method]['decl_class'])) {
            if (self::$debug) {
                self::debug("Restore also method $class::$method");
            }
            unset(self::$mocks[self::$mocks[$class][$method]['decl_class']][$method]);
            unset(self::$mocks_by_name[$method][$class]);
        }
        unset(self::$mocks[$class][$method]);
        unset(self::$mocks_by_name[$method][$class]);
        if ((self::$mocks_by_name[$method] ?? null) === []) {
            unset(self::$mocks_by_name[$method]);
        }
    }

    public static function restoreExit()
    {
        if (self::$debug) {
            self::debug("Restore exit language construct");
        }
        unset(self::$lang_construct_mocks[self::LANG_CONSTRUCT_EXIT]);
    }

    public static function redefineGenerator($class, $method, callable $replacement)
    {
        if (self::$paused) self::$paused_generator_mocks[$class][$method] = $replacement;
        else self::$generator_mocks[$class][$method] = $replacement;
    }

    public static function restoreGenerator($class, $method)
    {
        unset(self::$generator_mocks[$class][$method]);
    }

    public static function isGeneratorMocked($class, $method)
    {
        return isset(self::$generator_mocks[$class][$method]);
    }

    public static function getMockForGenerator($class, $method)
    {
        if (!isset(self::$generator_mocks[$class][$method])) {
            throw new \RuntimeException("Generator $class::$method is not mocked");
        }

        return self::$generator_mocks[$class][$method];
    }

    public static function redefineNew($class, callable $constructorFunc)
    {
        if (self::$paused) self::$paused_new_mocks[$class] = $constructorFunc;
        else self::$new_mocks[$class] = $constructorFunc;
    }

    public static function restoreNew($class)
    {
        unset(self::$new_mocks[$class]);
    }

    public static function restoreAllNew()
    {
        self::restoreAllNewActual();
        self::restoreAllNewPaused();
    }

    private static function restoreAllNewActual()
    {
        self::$new_mocks = [];
    }

    private static function restoreAllNewPaused()
    {
        self::$paused_new_mocks = [];
    }

    public static function redefineConstant($constantName, $value)
    {
        $constantName = ltrim($constantName, '\\');
        if (self::$debug) {
            self::debug("Asked to redefine constant $constantName to $value");
        }

        if (SoftMocksTraverser::isConstIgnored($constantName)) {
            throw new \RuntimeException("Constant $constantName cannot be mocked using Soft Mocks");
        }

        if (self::$paused) self::$paused_constant_mocks[$constantName] = $value;
        else self::$constant_mocks[$constantName] = $value;

        $constantBaseName = array_slice(explode('::', $constantName), -1)[0];
        self::$class_const_mocks_by_name[$constantBaseName][$constantName] = true;
    }

    public static function restoreConstant($constantName)
    {
        unset(self::$constant_mocks[$constantName]);
        unset(self::$removed_constants[$constantName]);

        $constantBaseName = array_slice(explode('::', $constantName), -1)[0];
        unset(self::$class_const_mocks_by_name[$constantBaseName][$constantName]);
        if ((self::$class_const_mocks_by_name[$constantBaseName] ?? null) === []) {
            unset(self::$class_const_mocks_by_name[$constantBaseName]);
        }
    }

    public static function restoreAllConstants()
    {
        self::restoreAllConstantsActual();
        self::restoreAllConstantsPaused();
        self::$class_const_mocks_by_name = [];
    }

    private static function restoreAllConstantsActual()
    {
        self::$constant_mocks = [];
        self::$removed_constants = [];
    }

    private static function restoreAllConstantsPaused()
    {
        self::$paused_constant_mocks = [];
        self::$paused_removed_constants = [];
    }

    public static function removeConstant($constantName)
    {
        unset(self::$constant_mocks[$constantName], self::$paused_constant_mocks[$constantName]);
        if (self::$paused) self::$paused_removed_constants[$constantName] = true;
        else self::$removed_constants[$constantName] = true;

        $constantBaseName = array_slice(explode('::', $constantName), -1)[0];
        self::$class_const_mocks_by_name[$constantBaseName][$constantName] = true;
    }

    // there can be a situation when usage of static is not suitable for mocking so we need additional checks here
    // see \Badoo\SoftMocksTest::testParentMismatch to see when getDeclaringClass check is needed
    private static function staticContextIsOk($self, $static, $method)
    {
        try {
            $Rm = new \ReflectionMethod($static, $method);
            $Dc = $Rm->getDeclaringClass();
            if (!$Dc) {
                if (self::$debug) {
                    self::debug("Failed to get geclaring class for $static::$method");
                }
                return false;
            }

            $decl_class = $Dc->getName();
            if ($decl_class === $self) {
                return true;
            }

            // In PHP 5.5 the declared class is actually correct class, but it never a trait.
            // So we need to find the actual trait then if it is applicable to the class
            $Dt = self::getDeclaringTrait($decl_class, $method);
            if (!$Dt) {
                if (self::$debug) {
                    self::debug("Failed to get geclaring trait for $static::$method ($decl_class::$method");
                }
                return false;
            }

            if ($Dt->getName() === $self) {
                return true;
            }
        } catch (\ReflectionException $e) {
            if (self::$debug) {
                self::debug("Failed to get reflection method for $static::$method: $e");
            }
        }

        return false;
    }

    private static function getDeclaringTrait($class, $method)
    {
        $Rc = new \ReflectionClass($class);

        foreach (self::recursiveGetTraits($Rc) as $Trait) {
            if ($Trait->hasMethod($method)) {
                return $Trait;
            }
        }

        return null;
    }

    /**
     * @param \ReflectionClass $Rc
     * @return \ReflectionClass[]
     */
    private static function recursiveGetTraits(\ReflectionClass $Rc)
    {
        foreach ($Rc->getTraits() as $Trait) {
            foreach (self::recursiveGetTraits($Trait) as $T) {
                yield $T;
            }

            yield $Trait;
        }
    }

    /**
     * @param $self
     * @param $static
     * @param $method
     * @return false|string code
     */
    public static function isMocked($self, $static, $method)
    {
        if (self::$temp_disable) {
            if (self::$debug) {
                self::debug("Temporarily disabling mock check: $self::$method (static = $static)");
            }
            self::$temp_disable = false;
            return false;
        }

        $ancestor = $static;
        do {
            if (isset(self::$mocks[$ancestor][$method]) && self::staticContextIsOk($self, $ancestor, $method)) {
                return self::$mocks[$ancestor][$method]['code'];
            }
        } while ($ancestor = get_parent_class($ancestor));

        // it is very hard to make "self" work incorrectly because "self" is just an alias for class name at compile time
        return isset(self::$mocks[$self][$method]) ? self::$mocks[$self][$method]['code'] : false;
    }

    /**
     * @param string $func
     * @return false|string code
     */
    public static function isFuncMocked($func)
    {
        return isset(self::$func_mocks[$func]['code']) ? self::$func_mocks[$func]['code'] : false;
    }

    public static function callOriginal($callable, $args, $class = null)
    {
        if (is_array($callable)) {
            if (is_object($callable[0])) {
                $obj = $callable[0];
                if (!$class) $class = get_class($obj);
            } else {
                $class = $callable[0];
            }

            $method = $callable[1];
        } else if (is_scalar($callable) && strpos($callable, '::') !== false) {
            list($class, $method) = explode("::", $callable);
        } else {
            return call_user_func_array($callable, $args);
        }

        try {
            $Rm = new \ReflectionMethod($class, $method);
            if ($Rm->isUserDefined()) { // we can only mock and check for mocks for user defined methods
                if (isset(self::$mocks_by_name[$method])) { // don't set temp_disable if isMocked won't be called
                    self::$temp_disable = true;
                }
            }
        } catch (\ReflectionException $e) {
            // do nothing, it is ok in this case because it means that mock disabling is not needed
        }

        if (isset($obj)) {
            return self::callMethod($obj, $class, $method, $args);
        } else {
            return self::callStaticMethod($class, $method, $args);
        }
    }

    /**
     * Generate code that parses function parameters that are specified as string $args
     *
     * @param $args
     * @param \ReflectionParameter[] $params
     * @return string
     */
    private static function generateCode($args, array $params)
    {
        $args = trim($args ?? '');
        if (!$args) return '';

        $codeArgs = '';

        $list = token_get_all("<?php " . $args);
        $params_toks = [];
        $i = 0;
        foreach ($list as $tok) {
            if ($tok === ',') {
                $i++;
                continue;
            }
            $params_toks[$i][] = $tok;
        }

        foreach ($params_toks as $i => $toks) {
            $isRef = false;
            $varName = false;
            $haveDefault = false;
            $default = "";
            $mode = 'var';

            foreach ($toks as $tok) {
                if ($tok === '&') {
                    $isRef = true;
                    continue;
                }

                if ($tok === '=') {
                    $haveDefault = true;
                    $mode = 'default';
                    continue;
                }

                if ($mode == 'default') {
                    $default .= is_array($tok) ? $tok[1] : $tok;
                    continue;
                }

                if ($tok[0] === T_VARIABLE) {
                    $varName = $tok[1];
                }
            }

            if ($haveDefault) {
                $codeArgs .= "if (count(\$mm_func_args) > $i) {\n";
            }

            if ($isRef && isset($params[$i])) {
                $param_name = $params[$i]->getName();
                if (ltrim($varName, '$') !== $param_name) {
                    $codeArgs .= "$varName = &\$$param_name;\n";
                }
            } else {
                $codeArgs .= "$varName = \$params[$i];\n";
            }

            if ($haveDefault) {
                $codeArgs .= "} else {\n";
                $codeArgs .= "$varName = $default;\n";
                $codeArgs .= "}\n";
            }
        }

        return $codeArgs;
    }

    protected static function debug($message)
    {
        echo $message . "\n";
    }

    public static function injectIntoPhpunit()
    {
        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection ClassConstantUsageCorrectnessInspection */
        $possible_class_groups = [
            [
                'file_loader' => \PHPUnit\Util\FileLoader::class,
                'filter' => \PHPUnit\Util\Filter::class,
            ],
            [
                'file_loader' => \PHPUnit\Util\Fileloader::class,
                'filter' => \PHPUnit\Util\Filter::class,
            ],
            [
                'file_loader' => \PHPUnit_Util_Fileloader::class,
                'filter' => \PHPUnit_Util_Filter::class,
            ],
        ];
        /** @noinspection PhpUndefinedClassInspection */
        /** @var \PHPUnit\Util\FileLoader[]|\PHPUnit\Util\Fileloader[]|\PHPUnit_Util_Fileloader[] $classes */
        $classes = null;
        foreach ($possible_class_groups as $possible_classes) {
            if (!class_exists($possible_classes['file_loader'], false)) {
                continue;
            }
            $classes = $possible_classes;
            break;
        }
        if (!$classes) {
            return;
        }

        /** @noinspection PhpUndefinedClassInspection */
        /** @var \PHPUnit\Util\FileLoader|\PHPUnit\Util\Fileloader|\PHPUnit_Util_Fileloader $file_loader */
        $file_loader = $classes['file_loader'];
        /** @noinspection PhpUndefinedClassInspection */
        /** @var \PHPUnit\Util\Filter|\PHPUnit_Util_Filter $filter */
        $filter = $classes['filter'];

        if (!is_callable([$file_loader, 'setFilenameRewriteCallback'])) {
            if (self::$debug) {
                self::debug("Cannot inject into phpunit: method setFilenameRewriteCallback not found");
            }

            return;
        }

        call_user_func([$file_loader, 'setFilenameRewriteCallback'], [self::class, 'rewrite']);

        call_user_func(
            [$file_loader, 'setFilenameRestoreCallback'],
            function ($filename) {
                return self::replaceFilename($filename, true);
            }
        );

        call_user_func(
            [$filter, 'setCustomStackTraceCallback'],
            function ($e) {
                ob_start();
                self::printBackTrace($e);
                return ob_get_clean();
            }
        );
    }
}

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

    private $in_interface = false;
    private $in_closure_level = 0;
    private $has_yield = false;
    private $cur_class = '';
    private $cur_class_stack = [];

    public function __construct($filename)
    {
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

    public function beforeAttribute()
    {
        $this->disable_const_rewrite_level++;
    }

    public function rewriteAttribute()
    {
        $this->disable_const_rewrite_level--;
    }

    public function beforeStmt_Interface(\PhpParser\Node\Stmt\Interface_ $Node)
    {
        $this->cur_class = $Node->name;
        $this->in_interface = true;
    }

    public function beforeStmt_Function(\PhpParser\Node\Stmt\Function_ $Node)
    {
        if ($this->cur_class) {
            array_push($this->cur_class_stack, $this->cur_class);
            $this->cur_class = false;
        }
    }

    public function rewriteStmt_Function()
    {
        if ($this->cur_class_stack) {
            $this->cur_class = array_pop($this->cur_class_stack);
        }
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
        $NewNode->setAttribute('startLine', $Node->getLine());

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
                $Par->var,
                null,
                $Par->byRef
            );
        }

        $ParamsArray = new \PhpParser\Node\Expr\Array_($params_arr);
        $ParamsArray->setAttribute('kind', \PhpParser\Node\Expr\Array_::KIND_SHORT);
        $body_stmts = [
            new \PhpParser\Node\Stmt\Expression(
                new \PhpParser\Node\Expr\Assign(
                    new \PhpParser\Node\Expr\Variable("mm_func_args"),
                    new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name("func_get_args"))
                )
            ),
            new \PhpParser\Node\Stmt\Expression(
                new \PhpParser\Node\Expr\Assign(
                    new \PhpParser\Node\Expr\Variable("params"),
                    $ParamsArray
                )
            ),
            new \PhpParser\Node\Stmt\Expression(
                new \PhpParser\Node\Expr\Assign(
                    new \PhpParser\Node\Expr\Variable("variadic_params_idx"),
                    new \PhpParser\Node\Scalar\String_((string)$variadic_params_idx)
                )
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

            $body_stmts[] = new \PhpParser\Node\Stmt\Expression(
                new \PhpParser\Node\Expr\Assign(
                    new \PhpParser\Node\Expr\Variable("mm_callback"),
                    new \PhpParser\Node\Expr\StaticCall(
                        new \PhpParser\Node\Name("\\" . SoftMocks::class),
                        "getMockForGenerator",
                        $args
                    )
                )
            );

            $func_call_args = [];
            foreach ($Node->params as $Par) {
                $func_call_args[] = new \PhpParser\Node\Arg($Par->var);
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
                    'stmts' => [
                        new \PhpParser\Node\Stmt\Expression(
                            new \PhpParser\Node\Expr\Yield_($val)
                        )
                    ],
                ]
            );

            $body_stmts[] = new \PhpParser\Node\Stmt\Return_();
        } else {
            $args = [
                new \PhpParser\Node\Arg(
                    new \PhpParser\Node\Expr\ClassConstFetch(
                        new \PhpParser\Node\Name((string)($this->cur_class ?: 'self')),
                        "class"
                    )
                ),
                $static,
                $function,
            ];

            $eval = new \PhpParser\Node\Expr\Eval_(
                new \PhpParser\Node\Expr\Variable("__softmocksvariableforcode")
            );

            if ($Node->returnType instanceof \PhpParser\Node\Identifier && $Node->returnType->name === 'void') {
                $body_stmts[] = new \PhpParser\Node\Stmt\Expression($eval);
                $body_stmts[] = new \PhpParser\Node\Stmt\Return_();
            } else {
                $body_stmts[] = new \PhpParser\Node\Stmt\Return_($eval);
            }
        }
        $body_stmts[] = new \PhpParser\Node\Name("/** @codeCoverageIgnore */");

        $mockCheckCondition = new \PhpParser\Node\Expr\BinaryOp\NotIdentical(
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
        );
        if (!$this->has_yield) {
            $mockCheckCondition = new \PhpParser\Node\Expr\BinaryOp\BooleanAnd(
                new \PhpParser\Node\Expr\Isset_(
                    [
                        new \PhpParser\Node\Expr\ArrayDimFetch(
                            new \PhpParser\Node\Expr\StaticPropertyFetch(
                                new \PhpParser\Node\Name("\\" . SoftMocks::class),
                                'mocks_by_name'
                            ),
                            $function
                        )
                    ]
                ),
                $mockCheckCondition
            );
        }

        $MockCheck = new \PhpParser\Node\Stmt\If_(
            $mockCheckCondition,
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
            if ($value->var instanceof \PhpParser\Node\Expr\Variable && $value->var->name === 'this') {
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
                $ArgsArray = new \PhpParser\Node\Expr\Array_($arr_args);
                $ArgsArray->setAttribute('kind', \PhpParser\Node\Expr\Array_::KIND_SHORT);
                return new \PhpParser\Node\Expr\FuncCall(
                    new \PhpParser\Node\Name(['', 'array_merge']),
                    [$ArgsArray, $unpacked_arg]
                );
            }

            $arg_name = null;
            if (!empty($arg->name)) {
                $arg_name = new String_($arg->name->name);
            }
            $arr_args[] = new \PhpParser\Node\Expr\ArrayItem(
                $arg->value,
                $arg_name,
                $is_ref
            );

            $i++;
        }

        $ResultArray = new \PhpParser\Node\Expr\Array_($arr_args);
        $ResultArray->setAttribute('kind', \PhpParser\Node\Expr\Array_::KIND_SHORT);
        return $ResultArray;
    }

    private static function nodeNameToArg($name)
    {
        if (is_scalar($name) || $name instanceof \PhpParser\Node\Identifier) {
            $name = new \PhpParser\Node\Scalar\String_($name);
        } else if ($name instanceof \PhpParser\Node\Name) {
            return new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\ClassConstFetch($name, 'class'));
        }

        return new \PhpParser\Node\Arg($name);
    }

    public function rewriteExpr_FuncCall(\PhpParser\Node\Expr\FuncCall $Node)
    {
        $arg_is_ref = [];

        if ($Node->name instanceof \PhpParser\Node\Name) {
            $str = $Node->name->toString();
            if (isset(self::$ignore_functions[$str])) {
                return null;
            }

            if (isset(SoftMocks::$internal_functions[$str])) {
                foreach ((new \ReflectionFunction($str))->getParameters() as $Param) {
                    $arg_is_ref[] = $Param->isPassedByReference();
                }
            }

            $name = new \PhpParser\Node\Scalar\String_($str);
        } else { // Expr
            $name = $Node->name;
        }

        $namespaceArg = self::getNamespaceArg();
        if ($Node->name instanceof \PhpParser\Node\Name\FullyQualified) {
            $namespaceArg = new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\String_(''));
        }

        $isFunctionName = $Node->name instanceof \PhpParser\Node\Name;
        $NewNode = new \PhpParser\Node\Expr\StaticCall(
            new \PhpParser\Node\Name("\\" . SoftMocks::class),
            $isFunctionName ? 'callFunction' : 'call',
            [
                $namespaceArg,
                $name,
                self::nodeArgsToArray($Node->args, $arg_is_ref),
            ]
        );

        foreach ($NewNode->args as $ArgNode) {
            $ArgNode->setAttribute('startLine', $Node->getStartLine());
            $ArgNode->setAttribute('endLine', $Node->getStartLine());
        }

        if ($isFunctionName) {
            // The code was calling a function using a static name. Instead of always calling SoftMocks::callFunction(),
            // we optimize this by first checking SoftMocks::$func_mocks_by_name[$baseFunctionName] to see if there is
            // a mock for that given function name. If no mock exists, we do the original call instead.
            // Before rewrite: someFunc($arg)
            // After rewrite: isset(SoftMocks::$func_mocks_by_name['someFunc']) ? SoftMocks::callFunction('', 'someFunc', [$arg]) : someFunc($arg)
            $baseFunctionName = array_slice(explode("\\", $Node->name->toString()), -1)[0];
            $NewNode = new \PhpParser\Node\Expr\Ternary(
                new \PhpParser\Node\Expr\Isset_(
                    [
                        new \PhpParser\Node\Expr\ArrayDimFetch(
                            new \PhpParser\Node\Expr\StaticPropertyFetch(
                                new \PhpParser\Node\Name("\\" . SoftMocks::class),
                                'func_mocks_by_name'
                            ),
                            new \PhpParser\Node\Scalar\String_($baseFunctionName)
                        )
                    ]
                ),
                $NewNode,
                $Node
            );
        }

        $NewNode->setAttribute('startLine', $Node->getStartLine());
        $NewNode->setAttribute('endLine', $Node->getStartLine());

        return $NewNode;
    }

    public function rewriteExpr_ConstFetch(\PhpParser\Node\Expr\ConstFetch $Node)
    {
        if ($this->disable_const_rewrite_level > 0) {
            return null;
        }

        $name = $Node->name->toString();

        if (isset(self::$ignore_constants[mb_strtolower($name)])) {
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

        $NewNode->setAttribute('startLine', $Node->getLine());
        return $NewNode;
    }

    public function rewriteExpr_ClassConstFetch(\PhpParser\Node\Expr\ClassConstFetch $Node)
    {
        if ($this->disable_const_rewrite_level > 0 || mb_strtolower($Node->name) === 'class') {
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

        $existsMockedConstForNameCondition = new \PhpParser\Node\Expr\Isset_(
            [
                new \PhpParser\Node\Expr\ArrayDimFetch(
                    new \PhpParser\Node\Expr\StaticPropertyFetch(
                        new \PhpParser\Node\Name("\\" . SoftMocks::class),
                        'class_const_mocks_by_name'
                    ),
                    new \PhpParser\Node\Scalar\String_($Node->name)
                )
            ]
        );

        $NewNode = new \PhpParser\Node\Expr\Ternary(
            $existsMockedConstForNameCondition,
            new \PhpParser\Node\Expr\StaticCall(
                new \PhpParser\Node\Name("\\" . SoftMocks::class),
                "getClassConst",
                $params
            ),
            $Node
        );

        $NewNode->setAttribute('startLine', $Node->getLine());
        return $NewNode;
    }

    public function rewriteScalar_Encapsed(\PhpParser\Node\Scalar\Encapsed $Node)
    {
        $parts = $Node->parts;

        $update_required = false;
        foreach ($parts as &$part) {
            if ($part instanceof \PhpParser\Node\Expr\StaticCall) {
                if (implode('\\', $part->class->parts) === '\\' . SoftMocks::class) {
                    $update_required = true;
                }
            } elseif ($part instanceof \PhpParser\Node\Scalar\EncapsedStringPart) {
                $NewNode = new \PhpParser\Node\Scalar\Encapsed([$part]);
                $NewNode->setAttribute('startLine', $part->getStartLine());
                $NewNode->setAttribute('endLine', $part->getEndLine());
                $part = $NewNode;
            }
        }
        unset($part);

        if ($update_required) {
            if (count($parts) > 1) {
                $NewNode = (new \PhpParser\BuilderFactory())->concat(...$parts);
                $NewNode->setAttribute('startLine', $Node->getStartLine());
                $NewNode->setAttribute('endLine', $Node->getEndLine());
                return $NewNode;
            } else {
                return $parts[0];
            }
        }

        return $Node;
    }
}
