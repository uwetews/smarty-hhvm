<?php
/**
 * Smarty Internal Plugin Function Call Handler
 *
 * @package    Smarty
 * @subpackage PluginsInternal
 * @author     Uwe Tews
 */

/**
 * This class does handles template functions defined with the {function} tag missing in cache file.
 * It can happen when the template function was called with the nocache option or within a nocache section.
 * The template function will be loaded from it's compiled template file, executed and added to the cache file
 * for later use.
 *
 * @package    Smarty
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Function_Call_Handler
{
    /**
     * This function handles calls to template functions defined by {function}
     * It does create a PHP function at the first call
     *
     * @param string                   $_name     template function name
     * @param Smarty_Internal_Template $_smarty_tpl
     * @param string                   $_function PHP function name
     * @param array                    $_params   Smarty variables passed as call parameter
     * @param bool                     $_nocache  nocache flag
     *
     * @return bool
     */
    public static function call($_name, Smarty_Internal_Template $_smarty_tpl, $_params, $_function, $_filepath)
    {
        $funcParam = $_smarty_tpl->context->templateFunctions[$_name];
        if (is_file($funcParam['compiled_filepath'])) {
            // read compiled file
            $code = file_get_contents($funcParam['compiled_filepath']);
            // grab template function
            if (preg_match("/\/\* {$_function} \*\/\s*public\s*([\S\s]*?)\/\*\/ {$_function} \*\//", $code, $match)) {
                // grab source info from file dependency
                preg_match("/\s*'{$funcParam['uid']}'([\S\s]*?)\),/", $code, $match1);
                unset($code);
                $output = '';
                // make PHP function known
                eval($match[1]);
                if (function_exists($_function)) {
                    // search cache file template
                    $tplPtr = $_smarty_tpl;
                    while (!isset($tplPtr->cached) && isset($tplPtr->parent)) {
                        $tplPtr = $tplPtr->parent;
                    }
                    // add template function code to cache file
                    if (isset($tplPtr->cached)) {
                        $cache = $tplPtr->cached;
                        $content = $cache->read($tplPtr);
                        if ($content) {
                            $hash = str_replace(array('.', ','), '_', uniqid('', true));
                            $oldHash = $cache->compiledTplObj->hash;;
                            $content = str_replace($oldHash, $hash, $content);
                            // check if we must update file dependency
                            if (!preg_match("/{$funcParam['uid']}([\S\s]*?)\);/", $content, $match2)) {
                                $content = preg_replace("/(\$resourceInfo = array \()/", "\\1{$match1[0]}", $content);
                            }
                            $content = str_replace('/* - end class - */', "\n" . $match[0] . "\n/* - end class - */", $content);
                            $cache->write($tplPtr, $content);
                            $cache->process($_smarty_tpl);
                            $_smarty_tpl->context->templateFunctions[$_name]['obj'] = $cache->compiledTplObj;
                        }
                    }
                    return true;
                }
            }
        }
        return false;
    }
}
