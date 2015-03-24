<?php
/**
 * Smarty Internal Plugin Template
 * This file contains the Smarty template engine
 *
 * @package    Smarty
 * @subpackage Template
 * @author     Uwe Tews
 */

/**
 * Main class with template data structures and methods
 *
 * @package    Smarty
 * @subpackage Template
 *
 */
class Smarty_Internal_Template extends Smarty_Internal_Data
{
    /**
     * Set this if you want different sets of cache files for the same
     * templates.
     *
     * @var string
     */
    public $cache_id = null;

    /**
     * Set this if you want different sets of compiled files for the same
     * templates.
     *
     * @var string
     */
    public $compile_id = null;

    /**
     * caching enabled
     *
     * @var bool
     */
    public $caching = false;

    /**
     * cache lifetime in seconds
     *
     * @var integer
     */
    public $cache_lifetime = 3600;

    /**
     * Template resource
     *
     * @var string
     */
    public $template_resource = null;

    /**
     * Saved template Id
     *
     * @var null|string
     */
    public $templateId = null;

    /**
     * flag if compiled template is invalid and must be (re)compiled
     *
     * @var bool
     */
    public $mustCompile = null;

    /**
     * variable filters
     *
     * @var array
     */
    public $variable_filters = array();

    /**
     * optional log of tag/attributes
     *
     * @var array
     */
    public $used_tags = array();

    /**
     * internal flag to allow relative path in child template blocks
     *
     * @var bool
     */
    public $allow_relative_path = false;

    /**
     * Smarty object
     *
     * @var Smarty
     */
    public $smarty = null;

    /**
     * Context object
     *
     * @var Smarty_Internal_Context
     */
    public $context = null;

    /**
     * Source object
     *
     * @var Smarty_Template_Source
     */
//   public $source = null;

    /**
     * Compiled object
     *
     * @var Smarty_Template_Compiled
     */
 //   public $compiled = null;

    /**
     * Cached object
     *
     * @var Smarty_Template_Cached
     */
    //public $cached = null;

    /**
     * Compiler object
     *
     * @var Smarty_Internal_SmartyTemplateCompiler
     */
    //public $compiler = null;

    /**
     * Create template data object
     * Some of the global Smarty settings copied to template scope
     * It load the required template resources and caching plugins
     *
     * @param string                   $template_resource template resource string
     * @param Smarty                   $smarty            Smarty instance
     * @param Smarty_Internal_Template $_parent           back pointer to parent object with variables or null
     * @param mixed                    $_cache_id         cache   id or null
     * @param mixed                    $_compile_id       compile id or null
     * @param bool                     $_caching          use caching?
     * @param int                      $_cache_lifetime   cache life-time in seconds
     */
    public function __construct($template_resource, $smarty, $_parent = null, $_cache_id = null, $_compile_id = null, $_caching = null, $_cache_lifetime = null)
    {
        $this->smarty = $smarty;
        // Smarty parameter
        $this->cache_id = $_cache_id === null ? $this->smarty->cache_id : $_cache_id;
        $this->compile_id = $_compile_id === null ? $this->smarty->compile_id : $_compile_id;
        $this->caching = $_caching === null ? $this->smarty->caching : $_caching;
        if ($this->caching === true) {
            $this->caching = Smarty::CACHING_LIFETIME_CURRENT;
        }
        $this->cache_lifetime = $_cache_lifetime === null ? $this->smarty->cache_lifetime : $_cache_lifetime;
        $this->parent = $_parent;
        // Template resource
        $this->template_resource = $template_resource;
    }

    /**
     * fetches rendered template
     *
     * @throws Exception
     * @throws SmartyException
     * @return string rendered template output
     */
    public function fetch()
    {
        return $this->render(false);
    }

    /**
     * displays a Smarty template
     */
    public function display()
    {
        $this->render(true);
    }

    /**
     * test if cache is valid
     *
     * @param  string|object $template   the resource handle of the template file or template object
     * @param  mixed         $cache_id   cache id to be used with this template
     * @param  mixed         $compile_id compile id to be used with this template
     * @param  object        $parent     next higher level of Smarty variables
     *
     * @return bool       cache status
     */
    public function isCached()
    {
        if ($this->smarty->force_compile || $this->smarty->force_cache || !$this->caching) {
            return false;
        }
        if (!isset($this->cached)) {
            $this->cached = Smarty_Template_Cached::load($this);
        }
        return $this->cached->valid;
    }

    /**
     * render template
     *
     * @param  bool                         $display       true: display, false: fetch
     * @param bool                          $isSubTemplate
     * @param bool                          $runOutputFilter
     * @param null|\Smarty_Internal_Context $_contextObjIn optional buffer object
     *
     * @return string
     * @throws \SmartyException
     */
    public function render($display = false, $isSubTemplate = false, $runOutputFilter = true, Smarty_Internal_Context $_contextObjIn = null)
    {
        $level = ob_get_level();
        try {
            if (!isset($this->source)) {
                $this->source = Smarty_Template_Source::load($this);
            }
            // checks if template exists
            if (!$this->source->exists) {
                if ($this->parent instanceof Smarty_Internal_Template) {
                    $parent_resource = " in '{$this->parent->template_resource}'";
                } else {
                    $parent_resource = '';
                }
                throw new SmartyException("Unable to load template {$this->source->type} '{$this->source->name}'{$parent_resource}");
            }
            if ($this->smarty->debugging) {
                Smarty_Internal_Debug::start_template($this);
            }
            $this->context = ($_contextObjIn === null) ? new Smarty_Internal_Context() : $_contextObjIn;
            // merge all variable scopes into template
            if (!$isSubTemplate) {
                $savedErrorLevel = isset($this->smarty->error_reporting) ? error_reporting($this->smarty->error_reporting) : null;
                // save local variables
                $savedTplVars = $this->tpl_vars;
                $savedConfigVars = $this->config_vars;
                $ptr_array = array($this);
                $ptr = $this;
                while (isset($ptr->parent)) {
                    $ptr_array[] = $ptr = $ptr->parent;
                }
                $ptr_array = array_reverse($ptr_array);
                $parent_ptr = reset($ptr_array);
                $tpl_vars = $parent_ptr->tpl_vars;
                $config_vars = $parent_ptr->config_vars;
                while ($parent_ptr = next($ptr_array)) {
                    if (!empty($parent_ptr->tpl_vars)) {
                        $tpl_vars = array_merge($tpl_vars, $parent_ptr->tpl_vars);
                    }
                    if (!empty($parent_ptr->config_vars)) {
                        $config_vars = array_merge($config_vars, $parent_ptr->config_vars);
                    }
                }
                if (!empty(Smarty::$global_tpl_vars)) {
                    $tpl_vars = array_merge(Smarty::$global_tpl_vars, $tpl_vars);
                }
                $this->tpl_vars = $tpl_vars;
                $this->config_vars = $config_vars;
            } else {
                $savedTplVars = null;
                $savedConfigVars = null;
                $savedErrorLevel = null;
            }
            // dummy local smarty variable
            if (!isset($this->tpl_vars['smarty'])) {
                $this->tpl_vars['smarty'] = new Smarty_Variable;
            }
            // disable caching for evaluated code
            if ($this->source->recompiled) {
                $this->caching = false;
            }
            $_caching = $this->caching == Smarty::CACHING_LIFETIME_CURRENT || $this->caching == Smarty::CACHING_LIFETIME_SAVED;
            // read from cache or render
            if ($_caching && !isset($this->cached)) {
                $this->cached = Smarty_Template_Cached::load($this);
            }
            if (!$_caching || !$this->cached->valid) {
                // render template (not loaded and not in cache)
                if ($this->smarty->debugging) {
                    Smarty_Internal_Debug::start_render($this, null);
                }
                if (isset($this->cached)) {
                    $_savedContext = $this->context;
                    $this->context = new Smarty_Internal_Context(true);
                }
                if (!$this->source->uncompiled) {
                    // render compiled code
                    if (!isset($this->compiled)) {
                        $this->compiled = Smarty_Template_Compiled::load($this);
                    }
                    $this->compiled->render($this);
                } else {
                    $this->source->renderUncompiled($this);
                }
                if ($this->smarty->debugging) {
                    Smarty_Internal_Debug::end_render($this, null);
                }
                // write to cache when necessary
                if ($_caching && !$this->source->recompiled) {
                    if ($this->smarty->debugging) {
                        Smarty_Internal_Debug::start_cache($this);
                    }
                    // write cache file content
                    if ($runOutputFilter && !$this->context->hasNocacheCode && (isset($this->smarty->autoload_filters['output']) || isset($this->smarty->registered_filters['output']))) {
                        $this->cached->writeCachedContent($this, $this->context, Smarty_Internal_Filter_Handler::runFilter('output', $this->context->getContent(), $this));
                    } else {
                        $this->cached->writeCachedContent($this, $this->context);
                    }
                    $this->context = $_savedContext;
                    $compile_check = $this->smarty->compile_check;
                    $this->smarty->compile_check = false;
                    if (!$this->cached->processed) {
                        $this->cached->process($this);
                    }
                    $this->smarty->compile_check = $compile_check;
                    $this->cached->compiledTplObj->getRenderedTemplateCode($this);
                    if ($this->smarty->debugging) {
                        Smarty_Internal_Debug::end_cache($this);
                    }
                }
            } else {
                if ($this->smarty->debugging) {
                    Smarty_Internal_Debug::start_cache($this);
                }
                $this->cached->render($this);
                if ($this->smarty->debugging) {
                    Smarty_Internal_Debug::end_cache($this);
                }
            }
            if ($runOutputFilter && (!$this->caching || $this->context->hasNocacheCode || $this->source->recompiled) && (isset($this->smarty->autoload_filters['output']) || isset($this->smarty->registered_filters['output']))) {
                $this->context->flushBuffer();
                $this->context->content = Smarty_Internal_Filter_Handler::runFilter('output', $this->context->content, $this);
            }
            // display or fetch
            if ($display) {
                $this->context->endBuffer();
                if ($this->caching && $this->smarty->cache_modified_check) {
                    $this->cached->cacheModifiedCheck($this, $this->context->content);
                } else {
                    echo $this->context->content;
                }
                $this->context = null;
            }
            if ($this->smarty->debugging) {
                Smarty_Internal_Debug::end_template($this);
            }
            if ($display) {
                // debug output
                if ($this->smarty->debugging) {
                    Smarty_Internal_Debug::display_debug($this, true);
                }
            }
            if (!$isSubTemplate) {
                // restore local variables
                $this->tpl_vars = $savedTplVars;
                $this->config_vars = $savedConfigVars;
                if (isset($savedErrorLevel)) {
                    error_reporting($savedErrorLevel);
                }
            }
        }
        catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        if (!$display && $_contextObjIn === null) {
            // return fetched content
            $this->context->endBuffer();
            $output = $this->context->content;
            $this->context = null;
            return $output;
        }
        $this->context = null;
        return '';
    }

    /**
     * Returns if the current template must be compiled by the Smarty compiler
     * It does compare the timestamps of template source and the compiled template and checks the force compile
     * configuration
     *
     * @throws SmartyException
     * @return bool true if the template must be compiled
     */
    public function mustCompile()
    {
        if (!isset($this->source)) {
            $this->loadSource();
        }
        if (!$this->source->exists) {
            if ($this->parent instanceof Smarty_Internal_Template) {
                $parent_resource = " in '$this->parent->template_resource}'";
            } else {
                $parent_resource = '';
            }
            throw new SmartyException("Unable to load template {$this->source->type} '{$this->source->name}'{$parent_resource}");
        }
        if ($this->mustCompile === null) {
            $this->mustCompile = (!$this->source->uncompiled && ($this->smarty->force_compile || $this->source->recompiled || $this->compiled->timestamp === false ||
                    ($this->smarty->compile_check && $this->compiled->timestamp < $this->source->timestamp)));
        }

        return $this->mustCompile;
    }

    /**
     * Compiles the template
     * If the template is not evaluated the compiled template is saved on disk
     */
    public function compileTemplateSource()
    {
        $this->loadCompiled();
        return $this->compiled->compileTemplateSource($this);
    }

    /**
     * Template code runtime function to create a local Smarty variable for array assignments
     *
     * @param string $tpl_var template variable name
     * @param bool   $nocache cache mode of variable
     * @param int    $scope   scope of variable
     */
    public function createLocalArrayVariable($tpl_var, $nocache = false, $scope = Smarty::SCOPE_LOCAL)
    {
        if (!isset($this->tpl_vars[$tpl_var])) {
            $this->tpl_vars[$tpl_var] = new Smarty_Variable(array(), $nocache, $scope);
        } else {
            $this->tpl_vars[$tpl_var] = clone $this->tpl_vars[$tpl_var];
            if ($scope != Smarty::SCOPE_LOCAL) {
                $this->tpl_vars[$tpl_var]->scope = $scope;
            }
            if (!(is_array($this->tpl_vars[$tpl_var]->value) || $this->tpl_vars[$tpl_var]->value instanceof ArrayAccess)) {
                settype($this->tpl_vars[$tpl_var]->value, 'array');
            }
        }
    }


    /**
     * Empty cache for this template
     *
     * @param integer $exp_time expiration time
     *
     * @return integer number of cache files deleted
     */
    public function clearCache($exp_time = null)
    {
        Smarty_CacheResource::invalidLoadedCache($this->smarty);

        return $this->cached->handler->clear($this->smarty, $this->template_resource, $this->cache_id, $this->compile_id, $exp_time);
    }

    /**
     * @param bool $caching
     */
    public function setCaching($caching)
    {
        $this->caching = $caching;
    }

    /**
     * @param int $cache_lifetime
     */
    public function setCacheLifetime($cache_lifetime)
    {
        $this->cache_lifetime = $cache_lifetime;
    }

    /**
     * @param string $compile_id
     */
    public function setCompileId($compile_id)
    {
        $this->compile_id = $compile_id;
    }

    /**
     * @param string $cache_id
     */
    public function setCacheId($cache_id)
    {
        $this->cache_id = $cache_id;
    }


    /**
     * Load source object
     *
     * @throws SmartyException
     */
    public function loadSource()
    {
        if (!isset($this->source)) {
            $this->source = Smarty_Template_Source::load($this);
        }
    }

    /**
     * Load compiled object
     *
     */
    public function loadCompiled()
    {
        if (!isset($this->compiled)) {
            $this->compiled = Smarty_Template_Compiled::load($this);
        }
    }

    /**
     * Load cached object
     *
     */
    public function loadCached()
    {
        if (!isset($this->cached)) {
            $this->cached = Smarty_Template_Cached::load($this);
        }
    }

    /**
     * Load compiler object
     *
     * @throws \SmartyException
     */
    public function loadCompiler()
    {
        $this->smarty->loadPlugin($this->source->compiler_class);
        $this->compiler = new $this->source->compiler_class($this->source->template_lexer_class, $this->source->template_parser_class, $this->smarty);
    }

    /**
     * set Smarty property in template context
     *
     * @param string $property_name property name
     * @param mixed  $value         value
     *
     * @throws SmartyException
     */
    public function __set($property_name, $value)
    {
        switch ($property_name) {
            case 'source':
            case 'compiled':
            case 'cached':
            case 'compiler':
                $this->$property_name = $value;

                return;

            // FIXME: routing of template -> smarty attributes
            default:
                if (property_exists($this->smarty, $property_name)) {
                    $this->smarty->$property_name = $value;

                    return;
                }
                throw new SmartyException("invalid template property '$property_name'.");
        }
    }

    /**
     * get Smarty property in template context
     *
     * @param string $property_name property name
     *
     * @return mixed
     * @throws SmartyException
     */
    public function __get($property_name)
    {
        switch ($property_name) {

            case 'source':
                $this->loadSource();
                return $this->source;

            case 'compiled':
                $this->compiled = Smarty_Template_Compiled::load($this);
                return $this->compiled;

            case 'cached':
                $this->cached = Smarty_Template_Cached::load($this);
                return $this->cached;

            case 'compiler':
                $this->smarty->loadPlugin($this->source->compiler_class);
                $this->compiler = new $this->source->compiler_class($this->source->template_lexer_class, $this->source->template_parser_class, $this->smarty);

                return $this->compiler;

            // FIXME: routing of template -> smarty attributes
            default:
                if (property_exists($this->smarty, $property_name)) {
                    return $this->smarty->$property_name;
                }
                throw new SmartyException("template property '$property_name' does not exist.");
        }
    }

    /**
     * Handle unknown class methods
     *
     * @param string $name unknown method-name
     * @param array  $args argument array
     *
     * @throws SmartyException
     */
    public function __call($name, $args)
    {
        static $_prefixes = array('set' => true, 'get' => true);
        static $_resolved_property_name = array();
        static $_resolved_property_source = array();

        // see if this is a set/get for a property
        $first3 = strtolower(substr($name, 0, 3));
        if (isset($_prefixes[$first3]) && isset($name[3]) && $name[3] !== '_') {
            $obj = $this;
            if (isset($_resolved_property_name[$name])) {
                $property_name = $_resolved_property_name[$name];
                if (isset($_resolved_property_source[$property_name]) && !$_resolved_property_source[$property_name]) {
                    $obj = $this->smarty;
                }
            } else {
                // try to keep case correct for future PHP 6.0 case-sensitive class methods
                // lcfirst() not available < PHP 5.3.0, so improvise
                $property_name = strtolower(substr($name, 3, 1)) . substr($name, 4);
                // convert camel case to underscored name
                $property_name = preg_replace_callback('/([A-Z])/', array($this->smarty, 'replaceCamelcase'), $property_name);
                if (property_exists($this, $property_name)) {
                    $_resolved_property_source[$property_name] = true;
                } elseif (property_exists($this->smarty, $property_name)) {
                    $_resolved_property_source[$property_name] = false;
                    $obj = $this->smarty;
                } else {
                    throw new SmartyException("property '$property_name' does not exist.");
                }
                $_resolved_property_name[$name] = $property_name;
            }
            if ($first3 == 'get') {
                return $obj->$property_name;
            } else {
                return $obj->$property_name = $args[0];
            }
        }
        // method of Smarty object?
        if (isset($this->smarty) && method_exists($this->smarty, $name)) {
            return call_user_func_array(array($this->smarty, $name), $args);
        }
        // must be unknown
        throw new SmartyException("Call of unknown method '$name'.");
    }

    /**
     * Clean values when cloning template object
     */
    public function __clone()
    {
        $this->parent = null;
        $this->context = null;
        $this->config_vars = array();
        $this->tpl_vars = array();
    }

    /**
     * Template data object destructor
     */
    public function __destruct()
    {
        if ($this->smarty->cache_locking && isset($this->cached) && $this->cached->is_locked) {
            $this->cached->handler->releaseLock($this->smarty, $this->cached);
        }
    }
}
