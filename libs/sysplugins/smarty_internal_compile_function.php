<?php
/**
 * Smarty Internal Plugin Compile Function
 * Compiles the {function} {/function} tags
 *
 * @package    Smarty
 * @subpackage Compiler
 * @author     Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Function Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Function extends Smarty_Internal_CompileBase
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
    public $optional_attributes = array('_any');

    /**
     * Compiles code for the {function} tag
     *
     * @param  array  $args      array with attributes from parser
     * @param \Smarty_Internal_SmartyTemplateCompiler $compiler  compiler object
     * @param  array  $parameter array with compilation parameter
     *
     * @return bool true
     * @throws \SmartyCompilerException
     */
    public function compile($args, Smarty_Internal_SmartyTemplateCompiler $compiler, $parameter)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $v = substr($compiler->lex->data,$compiler->lex->counter,$compiler->lex->counter+20);
        if ($_attr['nocache'] === true) {
            $compiler->trigger_template_error('nocache option not allowed', $compiler->lex->taglineno);
        }
        unset($_attr['nocache']);
        $this->openTag($compiler, 'function', array('function', $_attr, $compiler->parser->current_buffer, $compiler->lex->counter, $compiler->parent_compiler->plugins, $compiler->parent_compiler->nocachePlugins, $compiler->template->caching));
        // Init temporary context to compile function with caching disabled
        $compiler->parent_compiler->plugins = array();
        $compiler->parent_compiler->nocachePlugins = array();
        $compiler->parser->current_buffer = new Smarty_Internal_ParseTree_Template($compiler->parser);
        $compiler->template->caching = false;
        return true;
    }
}

/**
 * Smarty Internal Plugin Compile Functionclose Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Functionclose extends Smarty_Internal_CompileBase
{
    /**
     * Compiler object
     *
     * @var object
     */
    private $compiler = null;

    /**
     * Compiles code for the {/function} tag
     *
     * @param  array  $args      array with attributes from parser
     * @param \Smarty_Internal_SmartyTemplateCompiler $compiler  compiler object
     * @param  array  $parameter array with compilation parameter
     *
     * @return bool true
     */
    public function compile($args, Smarty_Internal_SmartyTemplateCompiler $compiler, $parameter)
    {
        $this->compiler = $compiler;
        list($tag, $_attr, $current_buffer, $counter, $plugins, $nocachePlugins, $caching) = $this->closeTag($compiler, array('function', '_function_nocache'));
        $_name = trim($_attr['name'], "'\"");
        if ($tag == 'function') {
            $compiler->parent_compiler->templateProperties['tpl_function'][$_name]['compiled_filepath'] = $compiler->parent_compiler->template->compiled->filepath;
            $compiler->parent_compiler->templateProperties['tpl_function'][$_name]['uid'] = $compiler->template->source->uid;
        }
        $_parameter = $_attr;
        unset($_parameter['name']);
        // default parameter
        $_paramsArray = array();
        foreach ($_parameter as $_key => $_value) {
            if (is_int($_key)) {
                $_paramsArray[] = "$_key=>$_value";
            } else {
                $_paramsArray[] = "'$_key'=>$_value";
            }
        }
        if (!empty($_paramsArray)) {
            $_params = 'array(' . implode(",", $_paramsArray) . ')';
            $_paramsCode = "array_merge($_params, \$params)";
        } else {
            $_paramsCode = '$params';
        }
        $_functionCode = $compiler->parser->current_buffer;
        // setup buffer for template function code
        $compiler->parser->current_buffer = new Smarty_Internal_ParseTree_Template($compiler->parser);
        $hash = str_replace(array('.', ','), '_', uniqid('', true));
        $_funcName = "smarty_template_function_{$_name}_{$hash}";
        if ($tag == 'function') {
            $compiler->parent_compiler->templateProperties['tpl_function'][$_name]['method'] = $_funcName;
            // assume function has no nocache code
            $compiler->parent_compiler->templateProperties['tpl_function'][$_name]['methodCaching'] = $_funcName;
            $output = "/* {$_funcName} */\n";
            //$output .= "if (!function_exists('{$_funcName}')) {\n";
            $output .= "public function {$_funcName}(\$_smarty_tpl, \$params) {\n";
            // build plugin include code
                foreach ($compiler->parent_compiler->plugins as $tmp) {
                    foreach ($tmp as $data) {
                        $output .= "if (!is_callable('{$data['function']}')) require_once '{$data['file']}';\n";
                    }
                }
            $output .= "\$saved_tpl_vars = \$_smarty_tpl->tpl_vars;\n";
            $output .= "foreach ({$_paramsCode} as \$key => \$value) {\n\$_smarty_tpl->tpl_vars[\$key] = new Smarty_Variable(\$value);\n}\n";
            $compiler->parser->current_buffer->append_subtree(new Smarty_Internal_ParseTree_Tag($compiler->parser, $output));
            $compiler->parser->current_buffer->append_subtree($_functionCode);
            $output = "foreach (Smarty::\$global_tpl_vars as \$key => \$value){\n";
            $output .= "if (\$_smarty_tpl->tpl_vars[\$key] === \$value) \$saved_tpl_vars[\$key] = \$value;\n}\n";
            $output .= "\$_smarty_tpl->tpl_vars = \$saved_tpl_vars;\n}\n";
            $output .= "/*/ {$_funcName} */\n\n";
            $compiler->parser->current_buffer->append_subtree(new Smarty_Internal_ParseTree_Tag($compiler->parser, $output));
            $compiler->parent_compiler->templateFunctionCode[] = $compiler->parser->current_buffer->to_smarty_php();
            if ($caching) {
                // if caching was originally enabled restart compiling the function with caching
                $compiler->lex->counter = $counter;
                $this->openTag($compiler, '_function_nocache', array('_function_nocache', $_attr, $current_buffer, 0, $plugins, $nocachePlugins, $caching));
                $compiler->parent_compiler->plugins = array();
                $compiler->parser->current_buffer = new Smarty_Internal_ParseTree_Template($compiler->parser);
                $compiler->template->caching = true;
                $compiler->has_nocache_code = false;
                return true;
            }
        }
        if ($tag == '_function_nocache' && $compiler->has_nocache_code) {
            // we found nocache code so we must save it.
            $_funcNameCaching = $_funcName . '_nocache';
            $compiler->parent_compiler->templateProperties['tpl_function'][$_name]['methodCaching'] = $_funcNameCaching;
                 $output = "/* {$_funcNameCaching} */\n";
                //$output .= "if (!function_exists('{$_funcNameCaching}')) {\n";
                $output .= "public function {$_funcNameCaching} (\$_smarty_tpl, \$params) {\n";
                foreach ($compiler->parent_compiler->plugins as $tmp) {
                    foreach ($tmp as $data) {
                        $output .= "if (!is_callable('{$data['function']}')) require_once '{$data['file']}';\n";
                    }
            }
                foreach ($compiler->parent_compiler->nocachePlugins as $tmp) {
                    foreach ($tmp as $data) {
                        $output .= "\$_smarty_tpl->context->addCacheCode('if (!is_callable(\'{$data['function']}\')) require_once \'{$data['file']}\';\n');\n";
                    }
            }
            //$output .= "ob_start();\n";
            $output .= "\$saved_tpl_vars = \$_smarty_tpl->tpl_vars;\n";
            $output .= "foreach ({$_paramsCode} as \$key => \$value) {\n\$_smarty_tpl->tpl_vars[\$key] = new Smarty_Variable(\$value);\n}\n";
            $output .= "\$_smarty_tpl->context->addCacheCode('\$saved_tpl_vars = \$_smarty_tpl->tpl_vars;\n');\n";
            $output .= "\$p = var_export({$_paramsCode}, true);\n";
            $output .= "\$_smarty_tpl->context->addCacheCode(\"foreach (\$p as \\\$key => \\\$value) {\n\\\$_smarty_tpl->tpl_vars[\\\$key] = new Smarty_Variable(\\\$value);\n}\n\");\n";
            $compiler->parser->current_buffer->append_subtree(new Smarty_Internal_ParseTree_Tag($compiler->parser, $output));
            $compiler->parser->current_buffer->append_subtree($_functionCode);
            $output = "\$_smarty_tpl->context->addCacheCode('foreach (Smarty::\$global_tpl_vars as \$key => \$value){\n');\n";
            $output .= "\$_smarty_tpl->context->addCacheCode('if (\$_smarty_tpl->tpl_vars[\$key] === \$value) \$saved_tpl_vars[\$key] = \$value;\n}\n');\n";
            $output .= "\$_smarty_tpl->context->addCacheCode('\$_smarty_tpl->tpl_vars = \$saved_tpl_vars;\n');\n";
            $output .= "foreach (Smarty::\$global_tpl_vars as \$key => \$value){\n";
            $output .= "if (\$_smarty_tpl->tpl_vars[\$key] === \$value) \$saved_tpl_vars[\$key] = \$value;\n}\n";
            $output .= "\$_smarty_tpl->tpl_vars = \$saved_tpl_vars;\n";
            //$output .= "echo ob_get_clean();\n}\n}\n";
            $output .= "\n}\n";
            $output .= "/*/ {$_funcNameCaching}_nocache */\n\n";
            $compiler->parser->current_buffer->append_subtree(new Smarty_Internal_ParseTree_Tag($compiler->parser, $output));
            $compiler->parent_compiler->templateFunctionCode[] = $compiler->parser->current_buffer->to_smarty_php();
        }
        $compiler->parser->current_buffer = $current_buffer;
        // restore old status
        $compiler->parent_compiler->plugins = $plugins;
        $compiler->parent_compiler->nocachePlugins = $nocachePlugins;
        $compiler->template->caching = $caching;
        return true;
    }
}
