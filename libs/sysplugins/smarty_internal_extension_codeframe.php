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
     * @param array                    $properties
     * @param  string                  $content optional template content
     * @param  string                  $header
     * @param  bool                    $cache   flag for cache file
     *
     * @return string
     * @throws \SmartyException
     */
    public static function create(Smarty_Internal_Template $_template, $properties, $content = '', $header = '', $cache = false)
    {
        // build property code
        $hash = str_replace(array('.', ','), '_', uniqid('', true));
        $class = "__Code_{$hash}_";
        $output = "<?php\n\n";
        $output .= $header . "\n\n";
        $output .= "if (!class_exists('{$class}',false)) {\n";
        $output .= "class {$class} extends Smarty_Internal_Runtime \n";
        $output .= "{\n";
        $version = Smarty::SMARTY_VERSION;
        $output .= "public \$version = '{$version}';\n";
        $output .= "public \$hash = '{$hash}';\n";
        foreach ($properties as $name => $value) {
            $output .= "public \${$name} = " . var_export($value, true) . ";\n";
        }
        $output .= "public function render (\$_smarty_tpl) {\n";
        $output .= $content;
        $output .= "}\n";
        if (!$cache) {
            if (!empty($_template->compiler->templateFunctionCode)) {
                $i = 0;
                while (isset($_template->compiler->templateFunctionCode[$i])) {
                    // run postfilter if required on compiled template code
                    if ((isset($_template->smarty->autoload_filters['post']) || isset($_template->smarty->registered_filters['post'])) && !$_template->compiler->suppressFilter) {
                        $output .= Smarty_Internal_Filter_Handler::runFilter('post', $_template->compiler->templateFunctionCode[$i], $_template);
                    } else {
                        $output .= $_template->compiler->templateFunctionCode[$i];
                    }
                    unset($_template->compiler->templateFunctionCode[$i]);
                    $output .= "\n";
                    $i ++;
                }
            }
            if (!empty($_template->compiler->mergedSubTemplatesCode)) {
                foreach ($_template->compiler->mergedSubTemplatesCode as $name => $code) {
                    unset($_template->compiler->mergedSubTemplatesCode[$name]);
                    $output .= $code;
                    $output .= "\n";
                }
            }
        } else {
            if (isset($_template->context)) {
                foreach ($_template->context->templateFunctions as $name => $funcParam) {
                    if (is_file($funcParam['compiled_filepath'])) {
                        // read compiled file
                        $code = file_get_contents($funcParam['compiled_filepath']);
                        // grab template function
                        if (preg_match("/\/\* {$funcParam['method']} \*\/\s*public\s*([\S\s]*?)\/\*\/ {$funcParam['method']} \*\//", $code, $match)) {
                            unset($code);
                            $output .= $match[0] . "\n";
                        }
                    }
                }
            }
        }
        $output .= "/* - end class - */\n}";
        $output .= "\n}\n";
        $output .= "\$this->compiledClass = '{$class}';\n";

        return self::format_string($output);
    }

    static function format_string($code = '')
    {
        $t_count = 0;
        $in_object = false;
        $in_at = false;
        $in_php = false;
        $isArray = false;
        $inArray = 0;

        $result = '';
        $tokens = token_get_all($code);
        foreach ($tokens as $token) {
            if (is_string($token)) {
                $token = trim($token);
                if ($token == '{') {
                    $t_count ++;
                    $result = rtrim($result) . ' ' . $token . "\n" . str_repeat("\t", $t_count);
                } elseif ($token == '}') {
                    $t_count --;
                    $result = rtrim($result) . "\n" . str_repeat("\t", $t_count) . $token . "\n" . str_repeat("\t", $t_count);
                } elseif ($token == ';') {
                    $result .= $token . "\n" . str_repeat("\t", $t_count);
                } elseif ($token == ':') {
                    $result .= ' ' . $token . ' ';
                } elseif ($token == '?') {
                    $result .= ' ' . $token . ' ';
                } elseif ($token == '(') {
                    if ($isArray || $inArray) {
                        $inArray ++;
                        $isArray = false;
                        $t_count ++;
                    }
                    $result .= $token;
                } elseif ($token == ')') {
                    if ($inArray) {
                        $inArray --;
                        $t_count --;
                    }
                    $result .= $token;
                } elseif ($token == '@') {
                    $in_at = true;
                    $result .= $token;
                } elseif ($token == '.') {
                    $result .= ' ' . $token . ' ';
                } elseif ($token == ',') {
                    $result .= $token;
                    if ($inArray) {
                        $result .= "\n" . str_repeat("\t", $t_count);
                    } else {
                        $result .= ' ';
                    }
                } elseif ($token == '=') {
                    $result .= ' ' . $token . ' ';
                } else {
                    $result .= $token;
                }
            } else {
                list ($id, $text) = $token;
                switch ($id) {
                    case T_OPEN_TAG:
                    case T_OPEN_TAG_WITH_ECHO:
                        $in_php = true;
                        $result .= trim($text) . "\n";
                        break;
                    case T_CLOSE_TAG:
                        $in_php = false;
                        $result .= trim($text);
                        break;
                    case T_OBJECT_OPERATOR:
                        $result .= trim($text);
                        $in_object = true;
                        break;
                    case T_STRING:
                        if ($in_object) {
                            $result = rtrim($result) . trim($text);
                            $in_object = false;
                        } elseif ($in_at) {
                            $result = rtrim($result) . trim($text);
                            $in_at = false;
                        } else {
                            $result = rtrim($result) . ' ' . trim($text);
                        }
                        break;
                    case T_ENCAPSED_AND_WHITESPACE:
                    case T_WHITESPACE:
                        $result .= trim($text);
                        break;
                    case T_FUNCTION:
                    case T_RETURN:
                    case T_ELSE:
                    case T_ELSEIF:
                        $result = rtrim($result) . ' ' . trim($text) . ' ';
                        break;
                    case T_CASE:
                    case T_DEFAULT:
                        $result = rtrim($result) . "\n" . str_repeat("\t", $t_count - 1) . trim($text) . ' ';
                        break;
                    case T_AND_EQUAL:
                    case T_AS:
                    case T_BOOLEAN_AND:
                    case T_BOOLEAN_OR:
                    case T_CONCAT_EQUAL:
                    case T_DIV_EQUAL:
                    case T_DOUBLE_ARROW:
                    case T_IS_EQUAL:
                    case T_IS_GREATER_OR_EQUAL:
                    case T_IS_IDENTICAL:
                    case T_IS_NOT_EQUAL:
                    case T_IS_NOT_IDENTICAL:
                        // case T_SMALLER_OR_EQUAL: // undefined constant ???
                    case T_LOGICAL_AND:
                    case T_LOGICAL_OR:
                    case T_LOGICAL_XOR:
                    case T_MINUS_EQUAL:
                    case T_MOD_EQUAL:
                    case T_MUL_EQUAL:
                    case T_OR_EQUAL:
                    case T_PLUS_EQUAL:
                    case T_SL:
                    case T_SL_EQUAL:
                    case T_SR:
                    case T_SR_EQUAL:
                    case T_START_HEREDOC:
                    case T_XOR_EQUAL:
                    case T_EXTENDS:
                    case T_INSTANCEOF:
                        $result = rtrim($result) . ' ' . trim($text) . ' ';
                        break;
                    case T_COMMENT:
                        $result = rtrim($result) . "\n" . str_repeat("\t", $t_count) . trim($text) . "\n" . str_repeat("\t", $t_count);
                        break;
                    case T_REQUIRE:
                    case T_INCLUDE:
                    case T_INCLUDE_ONCE:
                    case T_REQUIRE_ONCE:
                    case T_PUBLIC:
                    case T_PRIVATE:
                    case T_PROTECTED:
                    case T_STATIC:
                    case T_CLASS:
                    case T_ECHO:
                        $result .= trim($text) . ' ';
                        break;
                    case T_ARRAY:
                        $isArray = true;
                        $result .= $text;
                        break;
                    case T_INLINE_HTML:
                        $result .= $text;
                        break;
                    default:
                        $result .= trim($text);
                        break;
                } // switch($id) {
            } // if (is_string ($token)) {
        } // foreach ($tokens as $token) {
        return $result;
    }
}