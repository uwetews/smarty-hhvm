<?php
/**
 * Smarty Internal Plugin Nocache Insert
 * Compiles the {insert} tag into the cache file
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
class Smarty_Internal_Nocache_Insert
{
    /**
     * Compiles code for the {insert} tag into cache file
     *
     * @param  string                   $_function insert function name
     * @param  array                    $_attr     array with parameter
     * @param  Smarty_Internal_Template $_template template object
     * @param  string                   $_script   script name to load or 'null'
     * @param  string                   $_assign   optional variable name
     *
     * @return string                   compiled code
     */
    public static function compile($_function, $_attr, $_template, $_script, $_assign = null)
    {
        $_output = '';
        if ($_script != 'null') {
            // script which must be included
            // code for script file loading
            $_output .= "require_once '{$_script}';\n";
        }
        // call insert
        if (isset($_assign)) {
            $_output .= "\$_smarty_tpl->assign('{$_assign}' , {$_function} (" . var_export($_attr, true) . ",\$_smarty_tpl), true);\n";
        } else {
             $_output .= "{$_function}(" . var_export($_attr, true) . ",\$_smarty_tpl);\n";
        }
        $_tpl = $_template;
        while ($_tpl->parent instanceof Smarty_Internal_Template) {
            $_tpl = $_tpl->parent;
        }

        return $_output;
    }
}
