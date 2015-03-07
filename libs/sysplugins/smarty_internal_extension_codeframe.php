<?php
/**
 * Smarty Internal Extension
 * This file contains the Smarty template extension to create a code frame
 *
 * @package    Smarty
 * @subpackage Template
 * @author     Uwe Tews
 */

/**
 * Class Smarty_Internal_Extension_CodeFrame
 * Create code frame for compiled and cached templates

 */
class Smarty_Internal_Extension_CodeFrame
{
    /**
     * Create code frame for compiled and cached templates
     *
     * @param Smarty_Internal_Template $_template
     * @param  string                  $content optional template content
     * @param  bool                    $cache   flag for cache file
     *
     * @return string
     */
    public static function create(Smarty_Internal_Template $_template, $content = '', $header = '', $cache = false)
    {
        // build property code
        $_template->properties['has_nocache_code'] = $_template->has_nocache_code || !empty($_template->required_plugins['nocache']);
        $_template->properties['version'] = Smarty::SMARTY_VERSION;
        $hash = str_replace(array('.', ','), '_', uniqid('', true));
        if (!isset($_template->properties['unifunc'])) {
            $_template->properties['unifunc'] = 'content_' . $hash;
        }
        $output = "<?php\n";
        $output .= $header;
        $output .= "/*%%SmartyHeaderCode:{$hash}%%*/\n";
        if ($_template->smarty->direct_access_security) {
            $output .= "if(!defined('SMARTY_DIR')) exit('no direct access allowed');\n";
        }
        $output .= "\$_valid = \$_smarty_tpl->decodeProperties(" . var_export($_template->properties, true) . ',' . ($cache ? 'true' : 'false') . ");\n/*/%%SmartyHeaderCode%%*/\n";
        $output .= "if (\$_valid && !is_callable('{$_template->properties['unifunc']}')) {function {$_template->properties['unifunc']} (\$_smarty_tpl) {\n";
        // include code for plugins
        if (!$cache) {
            if (!empty($_template->required_plugins['compiled'])) {
                foreach ($_template->required_plugins['compiled'] as $tmp) {
                    foreach ($tmp as $data) {
                        $file = addslashes($data['file']);
                        if (is_Array($data['function'])) {
                            $output .= "if (!is_callable(array('{$data['function'][0]}','{$data['function'][1]}'))) require_once '{$file}';\n";
                        } else {
                            $output .= "if (!is_callable('{$data['function']}')) require_once '{$file}';\n";
                        }
                    }
                }
            }
            if (!empty($_template->required_plugins['nocache'])) {
                $_template->has_nocache_code = true;
                $output .= "\$_smarty_tpl->buffer->toBufferCacheCode('\$_smarty = \$_smarty_tpl->smarty;\n');\n";
                foreach ($_template->required_plugins['nocache'] as $tmp) {
                    foreach ($tmp as $data) {
                        $file = addslashes($data['file']);
                        if (is_Array($data['function'])) {
                            $c = addcslashes("if (!is_callable(array('{$data['function'][0]}','{$data['function'][1]}'))) require_once '{$file}';\n", '\'\\');
                        } else {
                            $c = addcslashes("if (!is_callable('{$data['function']}')) require_once '{$file}';\n", '\'\\');
                        }
                        $output .= "\$_smarty_tpl->buffer->toBufferCacheCode('{$c}');\n";
                    }
                }
            }
        }
        $output .= $content;
        $output .= "}\n}\n";
        return $output;
    }

    public static function createFunctionFrame(Smarty_Internal_Template $_template, $content = '')
    {
        $hash = str_replace(array('.', ','), '_', uniqid('', true));
        if (!isset($_template->properties['unifunc'])) {
            $_template->properties['unifunc'] = 'content_' . $hash;
        }
        $output = "if (\$_valid && !is_callable('{$_template->properties['unifunc']}')) {function {$_template->properties['unifunc']} (\$_smarty_tpl) {\n";
        $output .= $content;
        $output .= "}\n";
        $output .= "}\n";
        return $output;
    }
}