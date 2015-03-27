<?php
/**
 * Smarty Internal Plugin Compile Print Expression
 * Compiles any tag which will output an expression or variable
 *
 * @package    Smarty
 * @subpackage Compiler
 * @author     Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Print Expression Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Php extends Smarty_Internal_CompileBase
{
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('code', 'type');

    /**
     * Compiles code for generating output from any expression
     *
     * @param array                                 $args      array with attributes from parser
     * @param \Smarty_Internal_TemplateCompilerBase $compiler  compiler object
     * @param array                                 $parameter array with compilation parameter
     *
     * @return string
     * @throws \SmartyException
     */
    public function compile($args, Smarty_Internal_TemplateCompilerBase $compiler, $parameter)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $compiler->has_code = false;
        $this->asp_tags = (ini_get('asp_tags') != '0');
        if ($_attr['type'] == 'tag' && !($compiler->smarty instanceof SmartyBC)) {
            $compiler->trigger_template_error('{php}[/php} tags not allowed. Use SmartyBC to enable them', $compiler->lex->taglineno);
        }
        if ($_attr['type'] != 'tag') {
            if (isset($compiler->smarty->security_policy)) {
                $this->php_handling = $compiler->smarty->security_policy->php_handling;
            } else {
                $this->php_handling = $compiler->smarty->php_handling;
            }
            if ($this->php_handling == Smarty::PHP_REMOVE) {
                return '';
            } elseif ($this->php_handling == Smarty::PHP_QUOTE) {
                $compiler->parser->current_buffer->append_subtree(new Smarty_Internal_ParseTree_Text($compiler->parser, htmlspecialchars($_attr['code'], ENT_QUOTES)));
                return '';
            } elseif ($this->php_handling == Smarty::PHP_PASSTHRU || ($_attr['type'] == 'tag' && !$this->asp_tags)) {
                $compiler->parser->current_buffer->append_subtree(new Smarty_Internal_ParseTree_Text($compiler->parser, $_attr['code']));
                return '';
            }
        } else {
            $compiler->has_code = true;
            $ldel = preg_quote($compiler->smarty->left_delimiter, '#');
            $rdel = preg_quote($compiler->smarty->right_delimiter, '#');
            return preg_replace(array("#^{$ldel}\\s*php\\s*{$rdel}#", "#{$ldel}\\s*/\\s*php\\s*{$rdel}$#"), '', $_attr['code']);
        }
        if (!($compiler->smarty instanceof SmartyBC)) {
            $compiler->trigger_template_error('$smarty->php_handling PHP_ALLOW not allowed. Use SmartyBC to enable it', $compiler->lex->taglineno);
        }
        $compiler->has_code = true;
        if ($_attr['type'] == 'php') {
            return preg_replace(array('#^<\?(?:php\s+|=)?#', '#\?>$#'), '', $_attr['code']);
        } elseif ($_attr['type'] == 'asp') {
            return preg_replace(array('#^<%#', '#%>$#'), '', $_attr['code']);
        } elseif ($_attr['type'] == 'script') {
            return preg_replace(array('#^<script\s+language\s*=\s*["\']?\s*php\s*["\']?\s*>#', '#<\/script>$#'), '', $_attr['code']);
        }
        $compiler->has_code = false;
        return '';
    }
}
