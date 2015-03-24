<?php

/**
 * Smarty Internal Plugin Compile Block
 * Compiles the {block}{/block} tags
 *
 * @package    Smarty
 * @subpackage Compiler
 * @author     Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Block Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Block extends Smarty_Internal_CompileBase
{
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('name');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('name');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $option_flags = array('hide', 'nocache');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array();
    /**
     * nested child block names
     *
     * @var array
     */
    public static $nested_block_names = array();

    /**
     * Compiles code for the {block} tag
     *
     * @param  array                                  $args      array with attributes from parser
     * @param \Smarty_Internal_SmartyTemplateCompiler $compiler  compiler object
     * @param  array                                  $parameter array with compilation parameter
     *
     * @return bool true
     * @throws \SmartyCompilerException
     */
    public function compile($args, Smarty_Internal_SmartyTemplateCompiler $compiler, $parameter)
    {
        $childRoot = $compiler->blockTagNestingLevel == 0 && $compiler->inheritance_child;
        if ($childRoot) {
            $type = '_block_child_';
            $function = 'register';
        } else {
            $type = 'block';
            $function = 'call';
        }
        if ($compiler->blockTagNestingLevel || $compiler->inheritance_child) {
            $this->option_flags[] = 'append';
            $this->option_flags[] = 'prepend';
        }
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $_name = trim($_attr['name'], "\"'");
        $compiler->blockTagNestingLevel ++;
        $this->openTag($compiler, $type, array($_attr, $compiler->nocache));
        // must whole block be nocache ?
        $compiler->nocache = $compiler->nocache | $compiler->tag_nocache;
       // $compiler->suppressNocacheProcessing = true;
        if ($_attr['nocache'] === true) {
            //$compiler->trigger_template_error('nocache option not allowed', $compiler->lex->taglineno);
        }
         $compiler->has_code = true;
        $compiler->suppressNocacheProcessing = true;
        $compiler->includeChildBlock[$compiler->blockTagNestingLevel] = false;
        $cm = $compiler->template->caching ? 'true' : 'false';
        $_output = "\$this->{$function}Block ('{$_name}', \$_smarty_tpl, array('caching' => {$cm}, 'code' => function (\$_smarty_tpl, \$blockParam) {\n";
        return $_output;
     }

    /**
     * Compile saved child block source
     *
     * @param object $compiler compiler object
     * @param string $_name    optional name of child block
     *
     * @return string   compiled code of child block
     */
    static function compileChildBlock($compiler, $_name = null)
    {
        if (!$compiler->blockTagNestingLevel) {
            $compiler->trigger_template_error('{$smarty.block.child} cannot be called outside {block}{/block}', $compiler->lex->taglineno);
        }
        $compiler->has_code = true;
        $compiler->suppressNocacheProcessing = true;
        $compiler->includeChildBlock[$compiler->blockTagNestingLevel] = true;
        $_output = "\$this->callBlock (\$blockParam['name'], \$_smarty_tpl, \$blockParam);\n";
        return $_output;
    }

    /**
     * Compile $smarty.block.parent
     *
     * @param object $compiler compiler object
     * @param string $_name    optional name of child block
     *
     * @return string   compiled code of child block
     */
    static function compileParentBlock($compiler, $_name = null)
    {
        if (!$compiler->blockTagNestingLevel || !$compiler->inheritance_child) {#
            $message = $compiler->inheritance_child ? 'block' : 'template';
            $compiler->trigger_template_error('{$smarty.block.parent} cannot be called outside child ' . $message, $compiler->lex->taglineno);
        }
        $compiler->suppressNocacheProcessing = true;
        $compiler->has_code = true;
        $_output = "\$blockParam['parentCode'](\$_smarty_tpl, null);\n";
        return $_output;
    }
}

/**
 * Smarty Internal Plugin Compile BlockClose Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Blockclose extends Smarty_Internal_CompileBase
{
    /**
     * Compiles code for the {/block} tag
     *
     * @param  array                                  $args      array with attributes from parser
     * @param \Smarty_Internal_SmartyTemplateCompiler $compiler  compiler object
     * @param  array                                  $parameter array with compilation parameter
     *
     * @return bool true
     */
    public function compile($args, Smarty_Internal_SmartyTemplateCompiler $compiler, $parameter)
    {
        $this->compiler = $compiler;
        list($_attr, $nocache) = $this->closeTag($compiler, array('block', '_block_child_'));
        $_parameter = $_attr;
        foreach ($_parameter as $name => $stat) {
            if ($stat === false) {
                unset($_parameter[$name]);
            }
        }
        if ($compiler->includeChildBlock[$compiler->blockTagNestingLevel] == true) {
            $_parameter['includeChildBlock'] = 'true';
        }
            $compiler->has_code = true;
            array_shift(Smarty_Internal_Compile_Block::$nested_block_names);
            $_output = "},\n";
            foreach ($_parameter as $name => $stat) {
                if ($stat !== false) {
                    $_output .= "'{$name}' => {$stat},\n";
                }
            }
            $_output .= "));\n";
            $compiler->suppressNocacheProcessing = true;
            $compiler->nocache = $nocache;
            $compiler->blockTagNestingLevel --;
            return $_output;
     }
}
