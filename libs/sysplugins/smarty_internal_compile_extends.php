<?php

/**
 * Smarty Internal Plugin Compile extend
 * Compiles the {extends} tag
 *
 * @package    Smarty
 * @subpackage Compiler
 * @author     Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile extend Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Extends extends Smarty_Internal_CompileBase
{
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('file');
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('file');

    /**
     * Compiles code for the {extends} tag
     *
     * @param array                                          $args     array with attributes from parser
     * @param \Smarty_Internal_SmartyTemplateCompiler $compiler compiler object
     * @param                                                $parameter
     *
     * @return string compiled code
     * @throws \SmartyCompilerException
     */
    public function compile($args, Smarty_Internal_SmartyTemplateCompiler $compiler, $parameter)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        if ($_attr['nocache'] === true) {
            $compiler->trigger_template_error('nocache option not allowed', $compiler->lex->taglineno);
        }
        if (strpos($_attr['file'], '$_tmp') !== false) {
            $compiler->trigger_template_error('illegal value for file attribute', $compiler->lex->taglineno);
        }
        $compiler->extendsFileName = $_attr['file'];
        $compiler->inheritance_child = true;
        $compiler->has_code = false;
        return true;
    }
}
