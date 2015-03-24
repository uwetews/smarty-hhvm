<?php
/**
 * Smarty Plugin Runtime
 * This file contains the runtime object
 *
 * @package    Smarty
 * @subpackage Template
 * @author     Uwe Tews
 */

/**
 * class for the Smarty Runtime object
 * The Smarty runtime object does contain core properties for compiled and cached templates
 *
 * @package    Smarty
 * @subpackage Template
 */
class Smarty_Internal_Runtime
{
    /**
     * Hash code
     *
     * @var string
     */
    public $hash = '';

    /**
     * Smarty version which did generate template code
     *
     * @var string
     */
    public $version = '';

    /**
     * Source resource info
     *
     * @var array
     */
    public $resourceInfo = array();

    /**
     *
     * @var array
     */
    public $templateFunctions = array();

    /**
     * @var array
     */
    public $plugins = array();

    /**
     * @var array
     */
    public $nocachePlugins = array();

    /**
     * Template object
     *
     * @var null|\Smarty_Internal_Template
     */
    public $template = null;

    /**
     * @var bool
     */
    public $isInheritanceChild = false;
    /**
     * @var bool
     */
    public $isInheritanceRoot = false;
    /**
     * @var bool
     */
    public $isCache = false;

    /**
     * @var int
     */
    public $cacheLifetime = 0;

    /**
     * @var int
     */
    public $caching = 0;

    /**
     * @var bool
     */
    public $hasNocacheCode = false;
    /**
     * internal capture runtime stack
     *
     * @var array
     */
    public $_capture_stack = array();

    /**
     * @var int
     */
    public $callCnt = 0;

    /**
     * @var array
     */
    public static $loadedPlugins = array();

    /**
     * Constructor
     *
     * @param \Smarty_Internal_Template $template
     */
    public function  __construct(Smarty_Internal_Template $template)
    {
        $valid = true;
        if (!$template->source->recompiled) {
            if (Smarty::SMARTY_VERSION != $this->version) {
                // new version must rebuild
                $valid = false;
            } elseif ((int) $template->smarty->compile_check == 1 || $this->isCache && $template->smarty->compile_check === Smarty::COMPILECHECK_ON) {
                // check file dependencies at compiled code
                foreach ($this->resourceInfo as $r) {
                    if ($r[2] == 'file' || $r[2] == 'php') {
                        if ($template->source->filepath == $r[0]) {
                            // do not recheck current template
                            $mtime = $template->source->timestamp;
                        } else {
                            // file and php types can be checked without loading the respective resource handlers
                            if (is_file($r[0])) {
                                $mtime = filemtime($r[0]);
                            } else {
                                $valid = false;
                                break;
                            }
                        }
                    } else {
                        $source = Smarty_Resource::source(null, $template->smarty, $r[0]);
                        if ($source->exists) {
                            $mtime = $source->timestamp;
                        } else {
                            $valid = false;
                            break;
                        }
                    }
                    if (!$mtime || $mtime > $r[1]) {
                        $valid = false;
                        break;
                    }
                }
            }
            if ($this->isCache) {
                // CACHING_LIFETIME_SAVED cache expiry has to be validated here since otherwise we'd define the unifunc
                if ($this->cacheLifetime > 0 && ($template->caching === Smarty::CACHING_LIFETIME_SAVED || $this->caching === Smarty::CACHING_LIFETIME_SAVED) &&
                    (time() > ($template->cached->timestamp + $this->cacheLifetime))
                ) {
                    $valid = false;
                }
                $template->cached->valid = $valid;
            } else {
                $template->mustCompile = !$valid;
            }
        }
        if ($valid && !empty($this->plugins)) {
            $this->loadPlugins($this->plugins);
        }
    }

    /**
     * get rendered template content by calling compiled or cached template code
     *
     * @param \Smarty_Internal_Template $template
     * @param null                      $method
     *
     * @return string
     * @throws \Exception
     *
     */
    public function getRenderedTemplateCode(Smarty_Internal_Template $template, $method = null)
    {
        $level = ob_get_level();
        try {
            if ($hasSecurity = isset($template->smarty->security_policy)) {
                $template->smarty->security_policy->startTemplate($this);
            }
            $context = $template->context;
            if (isset($context->hashedTemplates[$this->hash])) {
                $context->hashedTemplates[$this->hash] ++;
            } else {
                if ($this->isCache) {
                    $context->hasNocacheCode = $this->hasNocacheCode | $context->hasNocacheCode;
                } else {
                    $context->resourceInfo = array_merge($context->resourceInfo, $this->resourceInfo);
                }
                if (isset($this->templateFunctions)) {
                    $functions = $this->templateFunctions;
                    foreach ($functions as $name => $param) {
                        $param['obj'] = $this;
                        $context->templateFunctions[$name] = $param;
                    }
                }
                $context->hashedTemplates[$this->hash] = 1;
            }
            if ($this->callCnt ++ == 2) {
                $templateId = isset($template->templateId) ? $template->templateId : $template->smarty->getTemplateId($template->template_resource, $template->cache_id, $template->compile_id);
                if (!isset($template->smarty->template_objects[$templateId])) {
                    $template->smarty->template_objects[$templateId] = $tpl = clone $template;
                }
            }
            if ($isInheritanceRoot = (!$context->inheritanceFlag && $this->isInheritanceChild)) {
                $savedInheritanceBlocks = $context->inheritanceBlocks;
                $context->inheritanceBlocks = array();
            }
            $context->inheritanceFlag = $this->isInheritanceChild;
            //
            // render compiled or saved template code
            //
            if (isset($method)) {
                $this->$method($template);
            } else {
                $this->render($template);
            }
            // any unclosed {capture} tags ?
            if (!empty($this->_capture_stack)) {
                $this->capture_error($template);
            }
            if ($hasSecurity) {
                $template->smarty->security_policy->exitTemplate($this);
            }
            if ($isInheritanceRoot) {
                $context->inheritanceBlocks = $savedInheritanceBlocks;
            }
            return;
        }
        catch (Exception $e) {
            $this->_capture_stack = array();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /**
     * Template code runtime function to get subtemplate content
     *
     * @param string                    $template       the resource handle of the template file
     * @param mixed                     $cache_id       cache id to be used with this template
     * @param mixed                     $compile_id     compile id to be used with this template
     * @param integer                   $caching        cache mode
     * @param integer                   $cache_lifetime life time of cache data
     * @param                           $data
     * @param                           $_scope
     * @param \Smarty_Internal_Template $parent
     * @param bool                      $newBuffer
     *
     * @return string template content
     * @throws \Exception
     *
     */
    public function getSubTemplate($template, $cache_id, $compile_id, $caching, $cache_lifetime, $data, $_scope, Smarty_Internal_Template $parent, $isChild = false, $newBuffer = false)
    {
        $tpl = $parent->smarty->setupTemplate($template, $cache_id, $compile_id, $caching, $cache_lifetime, $data, $_scope, $parent);
        if (!isset($tpl->source)) {
            $tpl->source = Smarty_Template_Source::load($tpl);
        }
        $tpl->source->isChild = $isChild;
        return $tpl->render(false, true, false, $newBuffer ? null : $parent->context);
    }

    /**
     * Template code runtime function to set up an inline subtemplate
     *
     * @param string  $template       the resource handle of the template file
     * @param mixed   $cache_id       cache id to be used with this template
     * @param mixed   $compile_id     compile id to be used with this template
     * @param integer $caching        cache mode
     * @param integer $cache_lifetime life time of cache data
     * @param array   $data           passed parameter template variables
     * @param         $_scope
     * @param         $parent
     * @param string  $content_func   name of content function
     *
     * @param bool    $newBuffer
     *
     * @return object template content
     */
    public function getInlineSubTemplate($template, $cache_id, $compile_id, $caching, $cache_lifetime, $data, $_scope, $parent, $params, $newBuffer = false)
    {
        $tpl = $parent->smarty->setupTemplate($template, $cache_id, $compile_id, $caching, $cache_lifetime, $data, $_scope, $parent);
        $tpl->context = $newBuffer ? new Smarty_Internal_Context() : $parent->context;
        if ($tpl->smarty->debugging) {
            Smarty_Internal_Debug::start_template($tpl);
            Smarty_Internal_Debug::start_render($tpl);
        }
        $this->getRenderedTemplateCode($tpl, $params['func']);
        if ($tpl->smarty->debugging) {
            Smarty_Internal_Debug::end_template($tpl);
            Smarty_Internal_Debug::end_render($tpl);
        }
        if ($newBuffer) {
            $output = $tpl->context->getContent();
            $tpl->context = null;
            return $output;
        }
        $tpl->context = null;
        return '';
    }

    /**
     * Call template function
     *
     * @param string                    $name        template function name
     * @param \Smarty_Internal_Template $_smarty_tpl template object
     * @param array                     $params      parameter array
     * @param                           $cm
     *
     * @throws \SmartyException
     *
     */
    public function callTemplateFunction($name, Smarty_Internal_Template $_smarty_tpl, $params, $cm)
    {
        if (isset($_smarty_tpl->context->templateFunctions[$name])) {
            if ($cm) {
                $function = $_smarty_tpl->context->templateFunctions[$name]['methodCaching'];
            } else {
                $function = $_smarty_tpl->context->templateFunctions[$name]['method'];
            }
            $obj = $_smarty_tpl->context->templateFunctions[$name]['obj'];
            if (is_callable(array($obj, $function))) {
                $obj->$function ($_smarty_tpl, $params);
                return;
            }
            // try to load template function dynamically
            if (Smarty_Internal_Function_Call_Handler::call($name, $_smarty_tpl, $params, $function, $_smarty_tpl->context->templateFunctions[$name]['compiled_filepath'])) {
                $obj = $_smarty_tpl->context->templateFunctions[$name]['obj'];
                if (is_callable(array($obj, $function))) {
                    $obj->$function ($_smarty_tpl, $params);
                    return;
                }
            }
        }
        throw new SmartyException("Unable to find template function '{$name}'");
    }

    /**
     * runtime error not matching capture tags
     */
    public function capture_error($template)
    {
        throw new SmartyException("Not matching {capture} open/close in \"{$template->source->resource}\"");
    }

    /**
     * @param $plugins
     */
    private function loadPlugins($plugins)
    {
        foreach ($plugins as $file => $function) {
            if (!isset(self::$loadedPlugins[$function])) {
                if (is_file($file) && !is_callable($function)) {
                    include $file;
                }
                self::$loadedPlugins[$function] = true;
            }
        }
    }

    /**
     * @param $name
     * @param $_smarty_tpl
     * @param $params
     */
    public function callBlock($name, $_smarty_tpl, $params)
    {
        $level = isset($params['level']) ? $params['level'] : 0;
        if (isset($_smarty_tpl->context->inheritanceBlocks[$name][$level])) {
            if (isset($params['includeChildBlock'])) {
                $params['level'] = $level;
                if (!isset($params['callChildBlock'])) {
                    $params['callChildBlock'] = true;
                    $params['code']($_smarty_tpl, $params);
                    return;
                }
            }
            $parentCode = $params['code'];
            $params = $_smarty_tpl->context->inheritanceBlocks[$name][$level];
            $params['level'] = $level + 1;
            $params['parentCode'] = $parentCode;
            $this->callBlock($name, $_smarty_tpl, $params);
        } else {
            if (isset($params['includeChildBlock'])) {
                if (isset($params['hide']) || isset($params['noChildBlock'])) {
                    return;
                }
                $params['noChildBlock'] = true;
            }
            if (isset($params['append'])) {
                $params['parentCode']($_smarty_tpl, array());
            }
            $params['code']($_smarty_tpl, $params);
            if (isset($params['prepend'])) {
                $params['parentCode']($_smarty_tpl, array());
            }
        }
    }

    /**
     * @param $name
     * @param $_smarty_tpl
     * @param $params
     */
    public function registerBlock($name, $_smarty_tpl, $params)
    {
        $params['name'] = $name;
        $context = $_smarty_tpl->context;
        if (!isset($context->inheritanceBlocks[$name])) {
            $context->inheritanceBlocks[$name][0] = $params;
        } else {
            array_unshift($context->inheritanceBlocks[$name], $params);
        }
    }

    /**
     * [util function] counts an array, arrayAccess/traversable or PDOStatement object
     *
     * @param  mixed $value
     *
     * @return int   the count for arrays and objects that implement countable, 1 for other objects that don't, and 0
     *               for empty elements
     */
    public function _count($value)
    {
        if (is_array($value) === true || $value instanceof Countable) {
            return count($value);
        } elseif ($value instanceof IteratorAggregate) {
            // Note: getIterator() returns a Traversable, not an Iterator
            // thus rewind() and valid() methods may not be present
            return iterator_count($value->getIterator());
        } elseif ($value instanceof Iterator) {
            return iterator_count($value);
        } elseif ($value instanceof PDOStatement) {
            return $value->rowCount();
        } elseif ($value instanceof Traversable) {
            return iterator_count($value);
        } elseif ($value instanceof ArrayAccess) {
            if ($value->offsetExists(0)) {
                return 1;
            }
        } elseif (is_object($value)) {
            return count($value);
        }

        return 0;
    }

    /**
     * On destruct make sure buffer level is reset
     */
    public function __destruct()
    {
        $i = 1;
    }
}