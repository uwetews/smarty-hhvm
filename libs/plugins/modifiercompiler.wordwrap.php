<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifierCompiler
 */

/**
 * Smarty wordwrap modifier plugin
 * Type:     modifier<br>
 * Name:     wordwrap<br>
 * Purpose:  wrap a string of text at a given length
 *
 * @link   http://smarty.php.net/manual/en/language.modifier.wordwrap.php wordwrap (Smarty online manual)
 * @author Uwe Tews
 *
 * @param array $params parameters
 * @param       $compiler
 *
 * @return string with compiled code
 */
function smarty_modifiercompiler_wordwrap($params, $compiler)
{
    if (!isset($params[1])) {
        $params[1] = 80;
    }
    if (!isset($params[2])) {
        $params[2] = '"\n"';
    }
    if (!isset($params[3])) {
        $params[3] = 'false';
    }
    if (Smarty::$_MBSTRING) {
        // must call plugin
        $function = $compiler->getPlugin('mb_wordwrap', Smarty::PLUGIN_SHARED);
    } else {
        $function = 'wordwrap';
    }

    return $function . '(' . $params[0] . ',' . $params[1] . ',' . $params[2] . ',' . $params[3] . ')';
}
