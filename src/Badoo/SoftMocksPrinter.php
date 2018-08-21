<?php
namespace Badoo;

/**
 * Class SoftMocksPrinter prints provided AST and tries to preserve line numbers.
 * It is implemented by writing custom pStmts() method and by copying other methods implementation
 * that insert new lines (SoftMocksPrinter needs full control over new line characters in order to function).
 * 
 * Because implementation of methods is just copy-pasted, it depends heavily on the specific version of
 * PHP Parser. Probably there exists a better way to do the same thing.
 */
class SoftMocksPrinter extends \PhpParser\PrettyPrinter\Standard
{
    private $cur_ln;

    /**
     * Pretty prints an array of nodes (statements) and indents them optionally.
     *
     * @param \PhpParser\Node[] $nodes  Array of nodes
     * @param bool   $indent Whether to indent the printed nodes
     *
     * @return string Pretty printed statements
     */
    protected function pStmts(array $nodes, $indent = true)
    {
        $result = '';

        foreach ($nodes as $node) {
            $row = "";

            $cur_ln = $this->cur_ln;

            $comments = $node->getAttribute('comments', array());
            $comments = !empty($comments) ? ($this->pComments($node->getAttribute('comments', array())) . "\n") : "";
            $this->cur_ln += substr_count($comments, "\n");

            if ($node->getLine() > $this->cur_ln) {
                $row .= str_repeat("\n", $node->getLine() - $this->cur_ln);
                $this->cur_ln += substr_count($row, "\n");
            }

            $row .= $comments
                . $this->p($node)
                . ($node instanceof \PhpParser\Node\Expr ? ';' : '');

            $this->cur_ln = $cur_ln + substr_count($row, "\n"); // get rid of cur_ln modifications in deeper context

            $result .= $row;
        }

        if ($indent) {
            return preg_replace('~\n(?!$|' . $this->noIndentToken . ')~', "\n    ", $result);
        } else {
            return $result;
        }
    }

    /**
     * @param \PhpParser\Comment[] $comments
     */
    protected function pComments(array $comments)
    {
        $formattedComments = [];

        foreach ($comments as $comment) {
            $reformattedText = $comment->getReformattedText();
            if (mb_orig_strpos($reformattedText, '/**') === 0) {
                $formattedComments[] = $reformattedText;
            }
        }

        return !empty($formattedComments) ? implode("\n", $formattedComments) : "";
    }

    /**
     * @param \PhpParser\Node[] $nodes
     */
    protected function pCommaSeparatedMultiline(array $nodes, $trailingComma)
    {
        $result = '';
        $lastIdx = count($nodes) - 1;
        foreach ($nodes as $idx => $node) {
            if ($node !== null) {
                $comments = $node->getAttribute('comments', array());
                if ($comments) {
                    $result .= $this->pComments($comments);
                }

                $result .= "\n" . $this->p($node);
            } else {
                $result .= "\n";
            }
            if ($trailingComma || $idx !== $lastIdx) {
                $result .= ',';
            }
        }

        return preg_replace('~\n(?!$|' . $this->noIndentToken . ')~', "\n    ", $result);
    }

    public function prettyPrintFile(array $stmts)
    {
        $this->cur_ln = 1;
        $this->preprocessNodes($stmts);
        $result = $this->pStmts($stmts, false);
        $result = $this->handleMagicTokens($result);
        return "<?php " . $result;
    }

    protected function p(\PhpParser\Node $node)
    {
        return $this->{'p' . $node->getType()}($node);
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
            if ($node->getAttribute('endLine') - ($node->getLine() + substr_count($res, "\n")) >= 0) {
                $prefix = str_repeat("\n", $node->getAttribute('endLine') - ($node->getLine() + substr_count($res, "\n")));
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
        $first_line = $nodes[0]->getAttribute('startLine');
        $last_line = $nodes[sizeof($nodes) - 1]->getAttribute('endLine');
        return $first_line === $last_line;
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

    public function pExpr_Closure(\PhpParser\Node\Expr\Closure $node)
    {
        return ($node->static ? 'static ' : '')
            . 'function ' . ($node->byRef ? '&' : '')
            . '(' . $this->pCommaSeparated($node->params) . ')'
            . (!empty($node->uses) ? ' use(' . $this->pCommaSeparated($node->uses) . ')' : '')
            . (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
            . ' {' . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_Namespace(\PhpParser\Node\Stmt\Namespace_ $node)
    {
        if ($this->canUseSemicolonNamespaces) {
            return 'namespace ' . $this->p($node->name) . ';' . $this->pStmts($node->stmts, false);
        } else {
            return 'namespace' . (null !== $node->name ? ' ' . $this->p($node->name) : '')
                . ' {' . $this->pStmts($node->stmts) . '}';
        }
    }

    public function pStmt_Interface(\PhpParser\Node\Stmt\Interface_ $node)
    {
        return 'interface ' . $node->name
            . (!empty($node->extends) ? ' extends ' . $this->pCommaSeparated($node->extends) : '')
            . '{' . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_Trait(\PhpParser\Node\Stmt\Trait_ $node)
    {
        return 'trait ' . $node->name
            . '{' . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_TraitUse(\PhpParser\Node\Stmt\TraitUse $node)
    {
        return 'use ' . $this->pCommaSeparated($node->traits)
            . (empty($node->adaptations) ? ';' : ' {' . $this->pStmts($node->adaptations) . '}');
    }

    public function pStmt_ClassMethod(\PhpParser\Node\Stmt\ClassMethod $node)
    {
        return $this->pModifiers($node->type)
            . 'function ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pCommaSeparated($node->params) . ')'
            . (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
            . (null !== $node->stmts ? '{' . $this->pStmts($node->stmts) . '}' : ';');
    }

    public function pStmt_Function(\PhpParser\Node\Stmt\Function_ $node)
    {
        return 'function ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pCommaSeparated($node->params) . ')'
            . (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
            . '{' . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_If(\PhpParser\Node\Stmt\If_ $node)
    {
        return 'if (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . '}'
            . $this->pImplode($node->elseifs)
            . (null !== $node->else ? $this->p($node->else) : '');
    }

    public function pStmt_ElseIf(\PhpParser\Node\Stmt\ElseIf_ $node)
    {
        return ' elseif (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_Else(\PhpParser\Node\Stmt\Else_ $node)
    {
        return ' else {' . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_For(\PhpParser\Node\Stmt\For_ $node)
    {
        return 'for ('
            . $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
            . $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
            . $this->pCommaSeparated($node->loop)
            . ') {' . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_Foreach(\PhpParser\Node\Stmt\Foreach_ $node)
    {
        return 'foreach (' . $this->p($node->expr) . ' as '
            . (null !== $node->keyVar ? $this->p($node->keyVar) . ' => ' : '')
            . ($node->byRef ? '&' : '') . $this->p($node->valueVar) . ') {'
            . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_While(\PhpParser\Node\Stmt\While_ $node)
    {
        return 'while (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_Do(\PhpParser\Node\Stmt\Do_ $node)
    {
        return 'do {' . $this->pStmts($node->stmts)
            . '} while (' . $this->p($node->cond) . ');';
    }

    public function pStmt_Switch(\PhpParser\Node\Stmt\Switch_ $node)
    {
        return 'switch (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->cases) . '}';
    }

    public function pStmt_Case(\PhpParser\Node\Stmt\Case_ $node)
    {
        return (null !== $node->cond ? 'case ' . $this->p($node->cond) : 'default') . ':'
            . $this->pStmts($node->stmts);
    }

    public function pStmt_TryCatch(\PhpParser\Node\Stmt\TryCatch $node)
    {
        return 'try {' . $this->pStmts($node->stmts) . '}'
            . $this->pImplode($node->catches)
            . ($node->finally !== null ? ' finally {' . $this->pStmts($node->finally->stmts) . '}' : '');
    }

    public function pStmt_Catch(\PhpParser\Node\Stmt\Catch_ $node)
    {
        return ' catch (' . $this->pImplode($node->types, ' | ') . ' $' . $node->var . ') {'
            . $this->pStmts($node->stmts) . '}';
    }

    public function pStmt_Break(\PhpParser\Node\Stmt\Break_ $node)
    {
        return 'break' . ($node->num !== null ? ' ' . $this->p($node->num) : '') . ';';
    }

    public function pStmt_Continue(\PhpParser\Node\Stmt\Continue_ $node)
    {
        return 'continue' . ($node->num !== null ? ' ' . $this->p($node->num) : '') . ';';
    }

    public function pStmt_Return(\PhpParser\Node\Stmt\Return_ $node)
    {
        return 'return' . (null !== $node->expr ? ' ' . $this->p($node->expr) : '') . ';';
    }

    public function pStmt_Throw(\PhpParser\Node\Stmt\Throw_ $node)
    {
        return 'throw ' . $this->p($node->expr) . ';';
    }

    public function pStmt_Label(\PhpParser\Node\Stmt\Label $node)
    {
        return $node->name . ':';
    }

    public function pStmt_Goto(\PhpParser\Node\Stmt\Goto_ $node)
    {
        return 'goto ' . $node->name . ';';
    }

    // Other

    public function pStmt_Echo(\PhpParser\Node\Stmt\Echo_ $node)
    {
        return 'echo ' . $this->pCommaSeparated($node->exprs) . ';';
    }

    public function pStmt_Static(\PhpParser\Node\Stmt\Static_ $node)
    {
        return 'static ' . $this->pCommaSeparated($node->vars) . ';';
    }

    public function pStmt_Global(\PhpParser\Node\Stmt\Global_ $node)
    {
        return 'global ' . $this->pCommaSeparated($node->vars) . ';';
    }

    public function pStmt_StaticVar(\PhpParser\Node\Stmt\StaticVar $node)
    {
        return '$' . $node->name
            . (null !== $node->default ? ' = ' . $this->p($node->default) : '');
    }

    public function pStmt_Unset(\PhpParser\Node\Stmt\Unset_ $node)
    {
        return 'unset(' . $this->pCommaSeparated($node->vars) . ');';
    }

    public function pStmt_HaltCompiler(\PhpParser\Node\Stmt\HaltCompiler $node)
    {
        return '__halt_compiler();' . $node->remaining;
    }

    // Helpers

    protected function pType($node)
    {
        return is_string($node) ? $node : $this->p($node);
    }

    protected function pClassCommon(\PhpParser\Node\Stmt\Class_ $node, $afterClassToken)
    {
        return $this->pModifiers($node->type)
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
        $this->cur_ln = $bak_line + substr_count($return, "\n");

        return $return;
    }
}
