<?php

/**
 * Smarty Internal Plugin Compile Insert
 * Compiles the {insert} tag
 *
 * @package    Smarty
 * @subpackage Compiler
 * @author     Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Insert Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Insert extends Smarty_Internal_CompileBase
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
     * Compiles code for the {insert} tag
     *
     * @param  array  $args     array with attributes from parser
     * @param  object $compiler compiler object
     *
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $_name = null;
        $_script = null;
        $_smarty_tpl = $compiler->template;
        $_output = '';
        // save possible attributes
        eval('$_name = ' . $_attr['name'] . ';');
        if (isset($_attr['assign'])) {
            if ($compiler->template->caching && !$compiler->nocache) {
                $compiler->trigger_template_error(" {insert... assign=...} function '{$_name}' can't be used if caching is enabled", $compiler->lex->taglineno);
            }
            // output will be stored in a smarty variable instead of being displayed
            $_assign = $_attr['assign'];
            // create variable to make sure that the compiler knows about its nocache status
            $compiler->template->tpl_vars[trim($_attr['assign'], "'")] = new Smarty_Variable(null, true);
        }
        if (isset($_attr['script'])) {
            // script which must be included
            $_function = "smarty_insert_{$_name}";
            $_filepath = false;
            eval('$_script = ' . $_attr['script'] . ';');
            if (!isset($compiler->smarty->security_policy) && file_exists($_script)) {
                $_filepath = $_script;
            } else {
                if (isset($compiler->smarty->security_policy)) {
                    $_dir = $compiler->smarty->security_policy->trusted_dir;
                } else {
                    $_dir = $compiler->smarty->trusted_dir;
                }
                if (!empty($_dir)) {
                    foreach ((array) $_dir as $_script_dir) {
                        $_script_dir = rtrim($_script_dir, '/\\') . DS;
                        if (file_exists($_script_dir . $_script)) {
                            $_filepath = $_script_dir . $_script;
                            break;
                        }
                    }
                }
            }
            if ($_filepath == false) {
                $compiler->trigger_template_error("{insert} missing script file '{$_script}'", $compiler->lex->taglineno);
            }
            // code for script file loading
            $_output .= "require_once '{$_filepath}' ;\n";
            require_once $_filepath;
            if (!is_callable($_function)) {
                $compiler->trigger_template_error(" {insert} function '{$_function}' is not callable in script file '{$_script}'", $compiler->lex->taglineno);
            }
        } else {
            $_filepath = 'null';
            $_function = "insert_{$_name}";
            // function in PHP script ?
            if (!is_callable($_function)) {
                // try plugin
                if (!$_function = $compiler->getPlugin($_name, 'insert')) {
                    $compiler->trigger_template_error("{insert} no function or plugin found for '{$_name}'", $compiler->lex->taglineno);
                }
            }
        }
        // delete {insert} standard attributes
        unset($_attr['name'], $_attr['assign'], $_attr['script'], $_attr['nocache']);
        // convert attributes into parameter array string
        $_paramsArray = array();
        foreach ($_attr as $_key => $_value) {
            $_paramsArray[] = "'$_key' => $_value";
        }
        $_params = 'array(' . implode(", ", $_paramsArray) . ')';
        if ($compiler->template->caching) {
            $compiler->tag_nocache = true;
        }
        // call insert
        if (isset($_assign)) {
//            if ($compiler->template->caching) {
//                $_output .= "echo Smarty_Internal_Nocache_Insert::compile ('{$_function}',{$_params}, \$_smarty_tpl, '{$_filepath}',{$_assign});\n";
//            } else {
                $_output .= "\$_smarty_tpl->assign({$_assign} , {$_function} ({$_params},\$_smarty_tpl), true);\n";
 //           }
        } else {
 //           if ($compiler->template->caching) {
//                $_output .= "echo Smarty_Internal_Nocache_Insert::compile ('{$_function}',{$_params}, \$_smarty_tpl, '{$_filepath}');\n";
//            } else {
                $_output .= "echo {$_function}({$_params},\$_smarty_tpl);\n";
//            }
        }

        return $_output;
    }
}
