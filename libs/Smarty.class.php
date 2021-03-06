<?php
/**
 * Project:     Smarty: the PHP compiling template engine
 * File:        Smarty.class.php
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * For questions, help, comments, discussion, etc., please join the
 * Smarty mailing list. Send a blank e-mail to
 * smarty-discussion-subscribe@googlegroups.com
 *
 * @link      http://www.smarty.net/
 * @copyright 2015 New Digital Group, Inc.
 * @copyright 2015 Uwe Tews
 * @author    Monte Ohrt <monte at ohrt dot com>
 * @author    Uwe Tews
 * @author    Rodney Rehm
 * @package   Smarty
 * @version   3.1-DEV
 */

/**
 * define shorthand directory separator constant
 */
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

/**
 * set SMARTY_DIR to absolute path to Smarty library files.
 * Sets SMARTY_DIR only if user application has not already defined it.
 */
if (!defined('SMARTY_DIR')) {
    define('SMARTY_DIR', dirname(__FILE__) . DS);
}

/**
 * set SMARTY_SYSPLUGINS_DIR to absolute path to Smarty internal plugins.
 * Sets SMARTY_SYSPLUGINS_DIR only if user application has not already defined it.
 */
if (!defined('SMARTY_SYSPLUGINS_DIR')) {
    define('SMARTY_SYSPLUGINS_DIR', SMARTY_DIR . 'sysplugins' . DS);
}
if (!defined('SMARTY_PLUGINS_DIR')) {
    define('SMARTY_PLUGINS_DIR', SMARTY_DIR . 'plugins' . DS);
}
if (!defined('SMARTY_MBSTRING')) {
    define('SMARTY_MBSTRING', function_exists('mb_get_info'));
}
if (!defined('SMARTY_RESOURCE_CHAR_SET')) {
    // UTF-8 can only be done properly when mbstring is available!
    /**
     * @deprecated in favor of Smarty::$_CHARSET
     */
    define('SMARTY_RESOURCE_CHAR_SET', SMARTY_MBSTRING ? 'UTF-8' : 'ISO-8859-1');
}
if (!defined('SMARTY_RESOURCE_DATE_FORMAT')) {
    /**
     * @deprecated in favor of Smarty::$_DATE_FORMAT
     */
    define('SMARTY_RESOURCE_DATE_FORMAT', '%b %e, %Y');
}

/**
 * Try loading the Smmarty_Internal_Data class
 * If we fail we must load Smarty's autoloader.
 * Otherwise we may have a global autoloader like Composer
 */
if (!class_exists('Smarty_Autoloader', false)) {
    if (!class_exists('Smarty_Internal_Data', true)) {
        require_once 'Autoloader.php';
        Smarty_Autoloader::registerBC();
    }
}

/**
 * Load always needed external class files
 */

if (!class_exists('Smarty_Internal_Data', false)) {
    require_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_data.php';
}
require_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_context.php';
require_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_runtime.php';
require_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_template.php';
require_once SMARTY_SYSPLUGINS_DIR . 'smarty_resource.php';
require_once SMARTY_SYSPLUGINS_DIR . 'smarty_variable.php';
require_once SMARTY_SYSPLUGINS_DIR . 'smarty_template_source.php';

/**
 * This is the main Smarty class
 *
 * @package Smarty
 */
class Smarty extends Smarty_Internal_Data
{
    /**#@+
     * constant definitions
     */

    /**
     * smarty version
     */
    const SMARTY_VERSION = '3.1.22-dev/8';

    /**
     * define variable scopes
     */
    const SCOPE_LOCAL = 0;
    const SCOPE_PARENT = 1;
    const SCOPE_ROOT = 2;
    const SCOPE_GLOBAL = 3;
    /**
     * define caching modes
     */
    const CACHING_OFF = 0;
    const CACHING_LIFETIME_CURRENT = 1;
    const CACHING_LIFETIME_SAVED = 2;
    /**
     * define constant for clearing cache files be saved expiration datees
     */
    const CLEAR_EXPIRED = - 1;

    /**
     * define compile check modes
     */
    const COMPILECHECK_OFF = 0;
    const COMPILECHECK_ON = 1;
    const COMPILECHECK_CACHEMISS = 2;
    /**
     * modes for handling of "<?php ... ?>" tags in templates.
     */
    const PHP_PASSTHRU = 0; //-> print tags as plain text
    const PHP_QUOTE = 1; //-> escape tags as entities
    const PHP_REMOVE = 2; //-> escape tags as entities
    const PHP_ALLOW = 3; //-> escape tags as entities
    /**
     * filter types
     */
    const FILTER_POST = 'post';
    const FILTER_PRE = 'pre';
    const FILTER_OUTPUT = 'output';
    const FILTER_VARIABLE = 'variable';
    /**
     * plugin types
     */
    const PLUGIN_FUNCTION = 'function';
    const PLUGIN_BLOCK = 'block';
    const PLUGIN_COMPILER = 'compiler';
    const PLUGIN_MODIFIER = 'modifier';
    const PLUGIN_MODIFIERCOMPILER = 'modifiercompiler';
    const PLUGIN_SHARED = 'shared';

    /**#@-*/

    /**
     * assigned global tpl vars
     */
    public static $global_tpl_vars = array();

    /**
     * error handler returned by set_error_hanlder() in Smarty::muteExpectedErrors()
     */
    public static $_previous_error_handler = null;
    /**
     * contains directories outside of SMARTY_DIR that are to be muted by muteExpectedErrors()
     */
    public static $_muted_directories = array('./templates_c/' => null, './cache/' => null);
    /**
     * Flag denoting if Multibyte String functions are available
     */
    public static $_MBSTRING = SMARTY_MBSTRING;
    /**
     * The character set to adhere to (e.g. "UTF-8")
     */
    public static $_CHARSET = SMARTY_RESOURCE_CHAR_SET;
    /**
     * The date format to be used internally
     * (accepts date() and strftime())
     */
    public static $_DATE_FORMAT = SMARTY_RESOURCE_DATE_FORMAT;
    /**
     * Flag denoting if PCRE should run in UTF-8 mode
     */
    public static $_UTF8_MODIFIER = 'u';

    /**
     * Flag denoting if operating system is windows
     */
    public static $_IS_WINDOWS = false;

    /**#@+
     * variables
     */
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
     * auto literal on delimiters with whitspace
     *
     * @var bool
     */
    public $auto_literal = true;
    /**
     * display error on not assigned variables
     *
     * @var bool
     */
    public $error_unassigned = false;
    /**
     * look up relative filepaths in include_path
     *
     * @var bool
     */
    public $use_include_path = false;
    /**
     * template directory
     *
     * @var array
     */
    private $template_dir = array('./templates/');
    /**
     * joined template directory string used in cache keys
     *
     * @var string
     */
    public $joined_template_dir = './templates/';
    /**
     * joined config directory string used in cache keys
     *
     * @var string
     */
    public $joined_config_dir = './configs/';
    /**
     * default template handler
     *
     * @var callable
     */
    public $default_template_handler_func = null;
    /**
     * default config handler
     *
     * @var callable
     */
    public $default_config_handler_func = null;
    /**
     * default plugin handler
     *
     * @var callable
     */
    public $default_plugin_handler_func = null;
    /**
     * compile directory
     *
     * @var string
     */
    private $compile_dir = './templates_c/';
    /**
     * plugins directory
     *
     * @var array
     */
    private $plugins_dir = null;
    /**
     * cache directory
     *
     * @var string
     */
    private $cache_dir = './cache/';
    /**
     * config directory
     *
     * @var array
     */
    private $config_dir = array('./configs/');
    /**
     * force template compiling?
     *
     * @var bool
     */
    public $force_compile = false;
    /**
     * check template for modifications?
     *
     * @var bool
     */
    public $compile_check = true;
    /**
     * use sub dirs for compiled/cached files?
     *
     * @var bool
     */
    public $use_sub_dirs = false;
    /**
     * allow ambiguous resources (that are made unique by the resource handler)
     *
     * @var bool
     */
    public $allow_ambiguous_resources = false;
    /**
     * merge compiled includes
     *
     * @var bool
     */
    public $merge_compiled_includes = false;
    /**
     * template inheritance merge compiled includes
     *
     * @var bool
     */
    public $inheritance_merge_compiled_includes = true;
    /**
     * force cache file creation
     *
     * @var bool
     */
    public $force_cache = false;
    /**
     * template left-delimiter
     *
     * @var string
     */
    public $left_delimiter = "{";
    /**
     * template right-delimiter
     *
     * @var string
     */
    public $right_delimiter = "}";
    /**#@+
     * security
     */
    /**
     * class name
     * This should be instance of Smarty_Security.
     *
     * @var string
     * @see Smarty_Security
     */
    public $security_class = 'Smarty_Security';
    /**
     * implementation of security class
     *
     * @var Smarty_Security
     */
    public $security_policy = null;
    /**
     * controls handling of PHP-blocks
     *
     * @var integer
     */
    public $php_handling = self::PHP_PASSTHRU;
    /**
     * controls if the php template file resource is allowed
     *
     * @var bool
     */
    public $allow_php_templates = false;
    /**
     * Should compiled-templates be prevented from being called directly?
     * {@internal
     * Currently used by Smarty_Internal_Template only.
     * }}
     *
     * @var bool
     */
    public $direct_access_security = true;
    /**#@-*/
    /**
     * debug mode
     * Setting this to true enables the debug-console.
     *
     * @var bool
     */
    public $debugging = false;
    /**
     * This determines if debugging is enable-able from the browser.
     * <ul>
     *  <li>NONE => no debugging control allowed</li>
     *  <li>URL => enable debugging when SMARTY_DEBUG is found in the URL.</li>
     * </ul>
     *
     * @var string
     */
    public $debugging_ctrl = 'NONE';
    /**
     * Name of debugging URL-param.
     * Only used when $debugging_ctrl is set to 'URL'.
     * The name of the URL-parameter that activates debugging.
     *
     * @var type
     */
    public $smarty_debug_id = 'SMARTY_DEBUG';
    /**
     * Path of debug template.
     *
     * @var string
     */
    public $debug_tpl = null;
    /**
     * When set, smarty uses this value as error_reporting-level.
     *
     * @var int
     */
    public $error_reporting = null;

    /**
     * Internal flag for getTags()
     *
     * @var bool
     */
    public $get_used_tags = false;

    /**#@+
     * config var settings
     */

    /**
     * Controls whether variables with the same name overwrite each other.
     *
     * @var bool
     */
    public $config_overwrite = true;
    /**
     * Controls whether config values of on/true/yes and off/false/no get converted to boolean.
     *
     * @var bool
     */
    public $config_booleanize = true;
    /**
     * Controls whether hidden config sections/vars are read from the file.
     *
     * @var bool
     */
    public $config_read_hidden = false;

    /**#@-*/

    /**#@+
     * resource locking
     */

    /**
     * locking concurrent compiles
     *
     * @var bool
     */
    public $compile_locking = true;
    /**
     * Controls whether cache resources should emply locking mechanism
     *
     * @var bool
     */
    public $cache_locking = false;
    /**
     * seconds to wait for acquiring a lock before ignoring the write lock
     *
     * @var float
     */
    public $locking_timeout = 10;

    /**#@-*/

    /**
     * resource type used if none given
     * Must be an valid key of $registered_resources.
     *
     * @var string
     */
    public $default_resource_type = 'file';
    /**
     * caching type
     * Must be an element of $cache_resource_types.
     *
     * @var string
     */
    public $caching_type = 'file';
    /**
     * internal config properties
     *
     * @var array
     */
    public $properties = array();
    /**
     * config type
     *
     * @var string
     */
    public $default_config_type = 'file';
    /**
     * cached template objects
     *
     * @var array
     */
    public $source_objects = array();
    /**
     * cached template objects
     *
     * @var array Smarty_Internal_Template
     */
    public $template_objects = array();

    /**
     * enable resource caching
     *
     * @var bool
     */
    public $resource_caching = true;
    /**
     * enable template resource caching
     *
     * @var bool
     */
    public $template_resource_caching = false;
    /**
     * check If-Modified-Since headers
     *
     * @var bool
     */
    public $cache_modified_check = false;
    /**
     * registered plugins
     *
     * @var array
     */
    public $registered_plugins = array();
    /**
     * plugin search order
     *
     * @var array
     */
    public $plugin_search_order = array('function', 'block', 'compiler', 'class');
    /**
     * registered objects
     *
     * @var array
     */
    public $registered_objects = array();
    /**
     * registered classes
     *
     * @var array
     */
    public $registered_classes = array();
    /**
     * registered filters
     *
     * @var array
     */
    public $registered_filters = array();
    /**
     * registered resources
     *
     * @var array
     */
    public $registered_resources = array();
    /**
     * resource handler cache
     *
     * @var array
     */
    public $_resource_handlers = array();
    /**
     * registered cache resources
     *
     * @var array
     */
    public $registered_cache_resources = array();
    /**
     * cache resource handler cache
     *
     * @var array
     */
    public $_cacheresource_handlers = array();
    /**
     * autoload filter
     *
     * @var array
     */
    public $autoload_filters = array();
    /**
     * default modifier
     *
     * @var array
     */
    public $default_modifiers = array();
    /**
     * autoescape variable output
     *
     * @var bool
     */
    public $escape_html = false;
    /**
     * global internal smarty vars
     *
     * @var array
     */
    public static $_smarty_vars = array();
    /**
     * start time for execution time calculation
     *
     * @var int
     */
    public $start_time = 0;
    /**
     * default file permissions
     *
     * @var int
     */
    public $_file_perms = 0644;
    /**
     * default dir permissions
     *
     * @var int
     */
    public $_dir_perms = 0771;
    /**
     * block tag hierarchy
     *
     * @var array
     */
    public $_tag_stack = array();
    /**
     * required by the compiler for BC
     *
     * @var string
     */
    public $_current_file = null;
    /**
     * internal flag to enable parser debugging
     *
     * @var bool
     */
    public $_parserdebug = false;

    /**
     * Cache of is_file results of loadPlugin()
     *
     * @var array
     */
    public $_is_file_cache = array();

    /**#@-*/

    /**
     * Initialize new Smarty object

     */
    public function __construct()
    {
        if (is_callable('mb_internal_encoding')) {
            mb_internal_encoding(Smarty::$_CHARSET);
        }
        $this->start_time = microtime(true);
        // check default dirs for overloading
        if ($this->template_dir[0] !== './templates/' || isset($this->template_dir[1])) {
            $this->setTemplateDir($this->template_dir);
        }
        if ($this->config_dir[0] !== './configs/' || isset($this->config_dir[1])) {
            $this->setConfigDir($this->config_dir);
        }
        if ($this->compile_dir !== './templates_c/') {
            unset(self::$_muted_directories['./templates_c/']);
            $this->setCompileDir($this->compile_dir);
        }
        if ($this->cache_dir !== './cache/') {
            unset(self::$_muted_directories['./cache/']);
            $this->setCacheDir($this->cache_dir);
        }
        if (isset($this->plugins_dir)) {
            $this->setPluginsDir($this->plugins_dir);
        } else {
            $this->setPluginsDir(SMARTY_PLUGINS_DIR);
        }

        $this->debug_tpl = 'file:' . dirname(__FILE__) . '/debug.tpl';
        if (isset($_SERVER['SCRIPT_NAME'])) {
            Smarty::$global_tpl_vars['SCRIPT_NAME'] = new Smarty_Variable($_SERVER['SCRIPT_NAME']);
        }

        // Check if we're running on windows
        Smarty::$_IS_WINDOWS = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // let PCRE (preg_*) treat strings as ISO-8859-1 if we're not dealing with UTF-8
        if (Smarty::$_CHARSET !== 'UTF-8') {
            Smarty::$_UTF8_MODIFIER = '';
        }
    }

    /**
     * fetches a rendered Smarty template
     *
     * @param  string $template         the resource handle of the template file or template object
     * @param  mixed  $cache_id         cache id to be used with this template
     * @param  mixed  $compile_id       compile id to be used with this template
     * @param  object $parent           next higher level of Smarty variables
     * @param  bool   $display          true: display, false: fetch
     * @param  bool   $merge_tpl_vars   not used - left for BC
     * @param  bool   $no_output_filter not used - left for BC
     *
     * @throws Exception
     * @throws SmartyException
     * @return string rendered template output
     */
    public function fetch($template, $cache_id = null, $compile_id = null, $parent = null, $display = false, $merge_tpl_vars = true, $no_output_filter = false)
    {
        // check URL debugging control
        if (!$this->debugging && $this->debugging_ctrl == 'URL') {
            Smarty_Internal_Debug::debugUrl($this);
        }
        if (!is_object($template)) {
            $template = $this->setupTemplate($template, $cache_id, $compile_id, $this->caching, $this->cache_lifetime, array(), null, $parent, $display ? 2 : 3);
        } else {
             if ((bool) $template->caching !== (bool) $this->caching) {
                unset($template->compiled);
            }
            // set caching in template object
            $template->caching = $this->caching;
        }
        // fetch template content
        return $template->render($display);
    }

    /**
     * displays a Smarty template
     *
     * @param string $template   the resource handle of the template file or template object
     * @param mixed  $cache_id   cache id to be used with this template
     * @param mixed  $compile_id compile id to be used with this template
     * @param object $parent     next higher level of Smarty variables
     */
    public function display($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {
        // display template
        $this->fetch($template, $cache_id, $compile_id, $parent, true);
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
    public function isCached($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {
        if ($this->force_compile || $this->force_cache  || !$this->caching) {
            return false;
        }
        if (!is_object($template)) {
            $template = $this->setupTemplate($template, $cache_id, $compile_id, $this->caching, $this->cache_lifetime, array(), null, $parent, 4);
        }
        // return cache status of template
        return $template->isCached();
    }



    /**
     * Check if a template resource exists
     *
     * @param  string $resource_name template name
     *
     * @return bool status
     */
    public function templateExists($resource_name)
    {
        // create template object
        $save = $this->template_objects;
        $tpl = new $this->template_class($resource_name, $this);
        $tpl->loadSource();
        // check if it does exists
        $result = $tpl->source->exists;
        $this->template_objects = $save;

        return $result;
    }

    /**
     * Returns a single or all global  variables
     *
     * @param  string $varname variable name or null
     *
     * @return string variable value or or array of variables
     */
    public function getGlobal($varname = null)
    {
        if (isset($varname)) {
            if (isset(self::$global_tpl_vars[$varname])) {
                return self::$global_tpl_vars[$varname]->value;
            } else {
                return '';
            }
        } else {
            $_result = array();
            foreach (self::$global_tpl_vars as $key => $var) {
                $_result[$key] = $var->value;
            }

            return $_result;
        }
    }

    /**
     * Empty cache folder
     *
     * @param  integer $exp_time expiration time
     * @param  string  $type     resource type
     *
     * @return integer number of cache files deleted
     */
    public function clearAllCache($exp_time = null, $type = null)
    {
        // load cache resource and call clearAll
        $_cache_resource = Smarty_CacheResource::load($this, $type);
        Smarty_CacheResource::invalidLoadedCache($this);

        return $_cache_resource->clearAll($this, $exp_time);
    }

    /**
     * Empty cache for a specific template
     *
     * @param  string  $template_name template name
     * @param  string  $cache_id      cache id
     * @param  string  $compile_id    compile id
     * @param  integer $exp_time      expiration time
     * @param  string  $type          resource type
     *
     * @return integer number of cache files deleted
     */
    public function clearCache($template_name, $cache_id = null, $compile_id = null, $exp_time = null, $type = null)
    {
        // load cache resource and call clear
        $_cache_resource = Smarty_CacheResource::load($this, $type);
        Smarty_CacheResource::invalidLoadedCache($this);

        return $_cache_resource->clear($this, $template_name, $cache_id, $compile_id, $exp_time);
    }

    /**
     * Loads security class and enables security
     *
     * @param  string|Smarty_Security $security_class if a string is used, it must be class-name
     *
     * @return Smarty                 current Smarty instance for chaining
     * @throws SmartyException        when an invalid class name is provided
     */
    public function enableSecurity($security_class = null)
    {
        if ($security_class instanceof Smarty_Security) {
            $this->security_policy = $security_class;

            return $this;
        } elseif (is_object($security_class)) {
            throw new SmartyException("Class '" . get_class($security_class) . "' must extend Smarty_Security.");
        }
        if ($security_class == null) {
            $security_class = $this->security_class;
        }
        if (!class_exists($security_class)) {
            throw new SmartyException("Security class '$security_class' is not defined");
        } elseif ($security_class !== 'Smarty_Security' && !is_subclass_of($security_class, 'Smarty_Security')) {
            throw new SmartyException("Class '$security_class' must extend Smarty_Security.");
        } else {
            $this->security_policy = new $security_class($this);
        }

        return $this;
    }

    /**
     * Disable security
     *
     * @return Smarty current Smarty instance for chaining
     */
    public function disableSecurity()
    {
        $this->security_policy = null;

        return $this;
    }

    /**
     * Set template directory
     *
     * @param  string|array $template_dir directory(s) of template sources
     *
     * @return Smarty       current Smarty instance for chaining
     */
    public function setTemplateDir($template_dir)
    {
        $this->template_dir = array();
        foreach ((array) $template_dir as $k => $v) {
            $this->template_dir[$k] = rtrim(strtr($v, '\\', '/'), '/') . '/';
        }
        $this->joined_template_dir = join(' # ', $this->template_dir);
        return $this;
    }

    /**
     * Add template directory(s)
     *
     * @param  string|array $template_dir directory(s) of template sources
     * @param  string       $key          of the array element to assign the template dir to
     *
     * @return Smarty          current Smarty instance for chaining
     * @throws SmartyException when the given template directory is not valid
     */
    public function addTemplateDir($template_dir, $key = null)
    {
        $this->_addDir('template_dir', $template_dir, $key);
        $this->joined_template_dir = join(' # ', $this->template_dir);
        return $this;
    }

    /**
     * Get template directories
     *
     * @param mixed $index index of directory to get, null to get all
     *
     * @return array|string list of template directories, or directory of $index
     */
    public function getTemplateDir($index = null)
    {
        if ($index !== null) {
            return isset($this->template_dir[$index]) ? $this->template_dir[$index] : null;
        }
        return (array) $this->template_dir;
    }

    /**
     * Set config directory
     *
     * @param $config_dir
     *
     * @return Smarty       current Smarty instance for chaining
     */
    public function setConfigDir($config_dir)
    {
        $this->config_dir = array();
        foreach ((array) $config_dir as $k => $v) {
            $this->config_dir[$k] = rtrim(strtr($v, '\\', '/'), '/') . '/';
        }
        $this->joined_config_dir = join(' # ', $this->config_dir);
        return $this;
    }

    /**
     * Add config directory(s)
     *
     * @param string|array $config_dir directory(s) of config sources
     * @param mixed        $key        key of the array element to assign the config dir to
     *
     * @return Smarty current Smarty instance for chaining
     */
    public function addConfigDir($config_dir, $key = null)
    {
        $this->_addDir('config_dir', $config_dir, $key);
        $this->joined_config_dir = join(' # ', $this->config_dir);
        return $this;
    }

    /**
     * Get config directory
     *
     * @param mixed $index index of directory to get, null to get all
     *
     * @return array|string configuration directory
     */
    public function getConfigDir($index = null)
    {
        if ($index !== null) {
            return isset($this->config_dir[$index]) ? $this->config_dir[$index] : null;
        }
        return (array) $this->config_dir;
    }

    /**
     * Set plugins directory
     *
     * @param  string|array $plugins_dir directory(s) of plugins
     *
     * @return Smarty       current Smarty instance for chaining
     */
    public function setPluginsDir($plugins_dir)
    {
        $this->plugins_dir = array();
        foreach ((array) $plugins_dir as $k => $v) {
            $this->plugins_dir[$k] = rtrim(strtr($v, '\\', '/'), '/') . '/';
        }
        $this->_is_file_cache = array();
        return $this;
    }

    /**
     * Adds directory of plugin files
     *
     * @param $plugins_dir
     *
     * @return Smarty current Smarty instance for chaining
     */
    public function addPluginsDir($plugins_dir)
    {
        $this->_addDir('plugins_dir', $plugins_dir);
        $this->plugins_dir = array_unique($this->plugins_dir);
        $this->_is_file_cache = array();
        return $this;
    }

    /**
     * Get plugin directories
     *
     * @return array list of plugin directories
     */
    public function getPluginsDir()
    {
        return (array) $this->plugins_dir;
    }

    /**
     * Set compile directory
     *
     * @param  string $compile_dir directory to store compiled templates in
     *
     * @return Smarty current Smarty instance for chaining
     */
    public function setCompileDir($compile_dir)
    {
        $this->compile_dir = rtrim(strtr($compile_dir, '\\', '/'), '/') . '/';
        if (!isset(Smarty::$_muted_directories[$this->compile_dir])) {
            Smarty::$_muted_directories[$this->compile_dir] = null;
        }

        return $this;
    }

    /**
     * Get compiled directory
     *
     * @return string path to compiled templates
     */
    public function getCompileDir()
    {
        return $this->compile_dir;
    }

    /**
     * Set cache directory
     *
     * @param  string $cache_dir directory to store cached templates in
     *
     * @return Smarty current Smarty instance for chaining
     */
    public function setCacheDir($cache_dir)
    {
        $this->cache_dir = rtrim(strtr($cache_dir, '\\', '/'), '/') . '/';
        if (!isset(Smarty::$_muted_directories[$this->cache_dir])) {
            Smarty::$_muted_directories[$this->cache_dir] = null;
        }

        return $this;
    }

    /**
     * Get cache directory
     *
     * @return string path of cache directory
     */
    public function getCacheDir()
    {
        return $this->cache_dir;
    }

    /**
     * add directories to given property name
     *
     * @param string       $dirName directory property name
     * @param string|array $dir     directory string or array of strings
     * @param mixed        $key     optional key
     */
    private function _addDir($dirName, $dir, $key = null)
    {
        // make sure we're dealing with an array
        $this->$dirName = (array) $this->$dirName;

        if (is_array($dir)) {
            foreach ($dir as $k => $v) {
                $v = rtrim(strtr($v, '\\', '/'), '/') . '/';
                if (is_int($k)) {
                    // indexes are not merged but appended
                    $this->{$dirName}[] = $v;
                } else {
                    // string indexes are overridden
                    $this->{$dirName}[$k] = $v;
                }
            }
        } else {
            $v = rtrim(strtr($dir, '\\', '/'), '/') . '/';
            if ($key !== null) {
                // override directory at specified index
                $this->{$dirName}[$key] = $v;
            } else {
                // append new directory
                $this->{$dirName}[] = $v;
            }
        }
    }

    /**
     * Set default modifiers
     *
     * @param  array|string $modifiers modifier or list of modifiers to set
     *
     * @return Smarty       current Smarty instance for chaining
     */
    public function setDefaultModifiers($modifiers)
    {
        $this->default_modifiers = (array) $modifiers;

        return $this;
    }

    /**
     * Add default modifiers
     *
     * @param  array|string $modifiers modifier or list of modifiers to add
     *
     * @return Smarty       current Smarty instance for chaining
     */
    public function addDefaultModifiers($modifiers)
    {
        if (is_array($modifiers)) {
            $this->default_modifiers = array_merge($this->default_modifiers, $modifiers);
        } else {
            $this->default_modifiers[] = $modifiers;
        }

        return $this;
    }

    /**
     * Get default modifiers
     *
     * @return array list of default modifiers
     */
    public function getDefaultModifiers()
    {
        return $this->default_modifiers;
    }

    /**
     * Set autoload filters
     *
     * @param  array  $filters filters to load automatically
     * @param  string $type    "pre", "output", … specify the filter type to set. Defaults to none treating $filters'
     *                         keys as the appropriate types
     *
     * @return Smarty current Smarty instance for chaining
     */
    public function setAutoloadFilters($filters, $type = null)
    {
        if ($type !== null) {
            $this->autoload_filters[$type] = (array) $filters;
        } else {
            $this->autoload_filters = (array) $filters;
        }

        return $this;
    }

    /**
     * Add autoload filters
     *
     * @param  array  $filters filters to load automatically
     * @param  string $type    "pre", "output", … specify the filter type to set. Defaults to none treating $filters'
     *                         keys as the appropriate types
     *
     * @return Smarty current Smarty instance for chaining
     */
    public function addAutoloadFilters($filters, $type = null)
    {
        if ($type !== null) {
            if (!empty($this->autoload_filters[$type])) {
                $this->autoload_filters[$type] = array_merge($this->autoload_filters[$type], (array) $filters);
            } else {
                $this->autoload_filters[$type] = (array) $filters;
            }
        } else {
            foreach ((array) $filters as $key => $value) {
                if (!empty($this->autoload_filters[$key])) {
                    $this->autoload_filters[$key] = array_merge($this->autoload_filters[$key], (array) $value);
                } else {
                    $this->autoload_filters[$key] = (array) $value;
                }
            }
        }

        return $this;
    }

    /**
     * Get autoload filters
     *
     * @param  string $type type of filter to get autoloads for. Defaults to all autoload filters
     *
     * @return array  array( 'type1' => array( 'filter1', 'filter2', … ) ) or array( 'filter1', 'filter2', …) if $type
     *                was specified
     */
    public function getAutoloadFilters($type = null)
    {
        if ($type !== null) {
            return isset($this->autoload_filters[$type]) ? $this->autoload_filters[$type] : array();
        }

        return $this->autoload_filters;
    }

    /**
     * return name of debugging template
     *
     * @return string
     */
    public function getDebugTemplate()
    {
        return $this->debug_tpl;
    }

    /**
     * set the debug template
     *
     * @param  string $tpl_name
     *
     * @return Smarty          current Smarty instance for chaining
     * @throws SmartyException if file is not readable
     */
    public function setDebugTemplate($tpl_name)
    {
        if (!is_readable($tpl_name)) {
            throw new SmartyException("Unknown file '{$tpl_name}'");
        }
        $this->debug_tpl = $tpl_name;

        return $this;
    }

    /**
     * creates a data object
     *
     * @param object $parent next higher level of Smarty variables
     * @param string $name   optional data block name
     *
     * @returns Smarty_Data data object
     */
    public function createData($parent = null, $name = null)
    {
        $dataObj = new Smarty_Data($parent, $this, $name);
        if ($this->debugging) {
            Smarty_Internal_Debug::register_data($dataObj);
        }
        return $dataObj;
    }

    /**
     * creates a template object
     *
     * @param  string $template   the resource handle of the template file
     * @param  mixed  $cache_id   cache id to be used with this template
     * @param  mixed  $compile_id compile id to be used with this template
     * @param  object $parent     next higher level of Smarty variables
     *
     * @return object template object
     */
    public function createTemplate($template, $cache_id = null, $compile_id = null, $parent = null)
     {
        $tpl = $this->setupTemplate($template, $cache_id, $compile_id, $this->caching, $this->cache_lifetime, array(), null, $parent, 1);
        $tpl->smarty = clone $tpl->smarty;
        return $tpl;
    }

    /**
     * Template code runtime function to set up an inline subtemplate
     *
     * @param string   $template       the resource handle of the template file
     * @param mixed    $cache_id       cache id to be used with this template
     * @param mixed    $compile_id     compile id to be used with this template
     * @param integer  $caching        cache mode
     * @param integer  $cache_lifetime life time of cache data
     * @param array    $data           passed parameter template variables
     * @param null|int $parent_scope   scope in which {include} should execute
     * @param null     $parent
     *
     * @return \Smarty_Internal_Template
     */
    public function setupTemplate($template, $cache_id, $compile_id, $caching, $cache_lifetime, $data = array(), $parent_scope = null, Smarty_Internal_Data $parent = null, $mode = null)
    {
        if ($mode !== null) {
            $data = array();
            if (isset($cache_id)) {
                if (is_object($cache_id)) {
                    $parent = $cache_id;
                    $cache_id = null;
                } elseif (is_array($cache_id)) {
                    $data = $cache_id;
                    $cache_id = null;
                }
            }
            if (isset($parent)) {
                if (is_array($parent)) {
                    $data = $parent;
                    if ($mode > 1) {
                        $parent = $this;
                    } else {
                        $parent = null;
                    }
                }
            } elseif ($mode > 1) {
                $parent = $this;
            }
        }
        $tpl = null;
        $_templateId = '';
        if ($this->template_resource_caching || !empty($this->template_objects) || $mode == 4) {
            $_templateId = $this->getTemplateId($template, $cache_id, $compile_id);
            // already in template cache?
            if (isset($this->template_objects[$_templateId])) {
                // clone cached template object because of possible recursive call
                $tpl = clone $this->template_objects[$_templateId];
                $tpl->parent = $parent;
                if ((bool) $tpl->caching !== (bool) $caching) {
                    unset($tpl->compiled);
                }
                $tpl->caching = $caching;
                $tpl->cache_lifetime = $cache_lifetime;
            }
        }
        if(!isset($tpl)) {
            $tpl = new $this->template_class($template, $this, $parent, $cache_id, $compile_id, $caching, $cache_lifetime);
            if (($this->template_resource_caching && $mode > 3) || $mode == 4) {
                $this->template_objects[$_templateId] = $tpl;
            }
        }
        if (isset($parent_scope)) {
            // get variables from calling scope
            if ($parent_scope == Smarty::SCOPE_LOCAL) {
                $tpl->tpl_vars = $parent->tpl_vars;
                $tpl->config_vars = $parent->config_vars;
            } elseif ($parent_scope == Smarty::SCOPE_PARENT) {
                $tpl->tpl_vars = &$parent->tpl_vars;
                $tpl->config_vars = &$parent->config_vars;
            } elseif ($parent_scope == Smarty::SCOPE_GLOBAL) {
                $tpl->tpl_vars = &Smarty::$global_tpl_vars;
                $tpl->config_vars = &$this->config_vars;
            } elseif ($parent_scope == Smarty::SCOPE_ROOT) {
                $ptr = $tpl->parent;
                while (!empty($ptr->parent)) {
                    $ptr = $ptr->parent;
                }
                $tpl->tpl_vars = &$ptr->tpl_vars;
                $tpl->config_vars = &$ptr->config_vars;
            }
        }
        if (!empty($data)) {
            // set up variable values
            foreach ($data as $_key => $_val) {
                $tpl->tpl_vars[$_key] = new Smarty_Variable($_val);
            }
        }
        if ($this->debugging) {
            Smarty_Internal_Debug::register_template($tpl);
        }
        return $tpl;
    }

    /**
     * Get unique template id
     *
     * @param string     $template_name
     * @param null|mixed $cache_id
     * @param null|mixed $compile_id
     *
     * @return string
     */
    public function getTemplateId($template_name, $cache_id = null, $compile_id = null)
    {
        $cache_id = isset($cache_id) ? $cache_id : $this->cache_id;
        $compile_id = isset($compile_id) ? $compile_id : $this->compile_id;
        if ($this->allow_ambiguous_resources) {
            $_templateId = Smarty_Resource::getUniqueTemplateName($this, $template_name) . "#{$cache_id}#{$compile_id}";
        } else {
            $_templateId = $this->joined_template_dir . "#{$template_name}#{$cache_id}#{$compile_id}";
        }
        if (isset($_templateId[150])) {
            $_templateId = sha1($_templateId);
        }
        return $_templateId;
    }

    /**
     * Takes unknown classes and loads plugin files for them
     * class name format: Smarty_PluginType_PluginName
     * plugin filename format: plugintype.pluginname.php
     *
     * @param  string $plugin_name class plugin name to load
     * @param  bool   $check       check if already loaded
     *
     * @throws SmartyException
     * @return string |bool filepath of loaded file or false
     */
    public function loadPlugin($plugin_name, $check = true)
    {
        // if function or class exists, exit silently (already loaded)
        if ($check && (is_callable($plugin_name) || class_exists($plugin_name, false))) {
            return true;
        }
        // Plugin name is expected to be: Smarty_[Type]_[Name]
        $_name_parts = explode('_', $plugin_name, 3);
        // class name must have three parts to be valid plugin
        // count($_name_parts) < 3 === !isset($_name_parts[2])
        if (!isset($_name_parts[2]) || strtolower($_name_parts[0]) !== 'smarty') {
            throw new SmartyException("plugin {$plugin_name} is not a valid name format");
        }
        // if type is "internal", get plugin from sysplugins
        if (strtolower($_name_parts[1]) == 'internal') {
            $file = SMARTY_SYSPLUGINS_DIR . strtolower($plugin_name) . '.php';
            if (isset($this->_is_file_cache[$file]) ? $this->_is_file_cache[$file] : $this->_is_file_cache[$file] = is_file($file)) {
                require_once($file);
                return $file;
            } else {
                return false;
            }
        }
        // plugin filename is expected to be: [type].[name].php
        $_plugin_filename = "{$_name_parts[1]}.{$_name_parts[2]}.php";

        $_stream_resolve_include_path = function_exists('stream_resolve_include_path');

        // loop through plugin dirs and find the plugin
        foreach ($this->getPluginsDir() as $_plugin_dir) {
            $names = array(
                $_plugin_dir . $_plugin_filename,
                $_plugin_dir . strtolower($_plugin_filename),
            );
            foreach ($names as $file) {
                if (isset($this->_is_file_cache[$file]) ? $this->_is_file_cache[$file] : $this->_is_file_cache[$file] = is_file($file)) {
                    require_once($file);
                    return $file;
                }
                if ($this->use_include_path && !preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $_plugin_dir)) {
                    // try PHP include_path
                    if ($_stream_resolve_include_path) {
                        $file = stream_resolve_include_path($file);
                    } else {
                        $file = Smarty_Internal_Get_Include_Path::getIncludePath($file);
                    }

                    if ($file !== false) {
                        require_once($file);

                        return $file;
                    }
                }
            }
        }
        // no plugin loaded
        return false;
    }

    /**
     * Compile all template files
     *
     * @param  string $extension     file extension
     * @param  bool   $force_compile force all to recompile
     * @param  int    $time_limit
     * @param  int    $max_errors
     *
     * @return integer number of template files recompiled
     */
    public function compileAllTemplates($extension = '.tpl', $force_compile = false, $time_limit = 0, $max_errors = null)
    {
        return Smarty_Internal_Utility::compileAllTemplates($extension, $force_compile, $time_limit, $max_errors, $this);
    }

    /**
     * Compile all config files
     *
     * @param  string $extension     file extension
     * @param  bool   $force_compile force all to recompile
     * @param  int    $time_limit
     * @param  int    $max_errors
     *
     * @return integer number of template files recompiled
     */
    public function compileAllConfig($extension = '.conf', $force_compile = false, $time_limit = 0, $max_errors = null)
    {
        return Smarty_Internal_Utility::compileAllConfig($extension, $force_compile, $time_limit, $max_errors, $this);
    }

    /**
     * Delete compiled template file
     *
     * @param  string  $resource_name template name
     * @param  string  $compile_id    compile id
     * @param  integer $exp_time      expiration time
     *
     * @return integer number of template files deleted
     */
    public function clearCompiledTemplate($resource_name = null, $compile_id = null, $exp_time = null)
    {
        return Smarty_Internal_Utility::clearCompiledTemplate($resource_name, $compile_id, $exp_time, $this);

    }

    /**
     * Return array of tag/attributes of all tags used by an template
     *
     * @param Smarty_Internal_Template $template
     *
     * @return array  of tag/attributes
     */
    public function getTags(Smarty_Internal_Template $template)
    {
        return Smarty_Internal_Utility::getTags($template);
    }

    /**
     * Run installation test
     *
     * @param  array $errors Array to write errors into, rather than outputting them
     *
     * @return bool true if setup is fine, false if something is wrong
     */
    public function testInstall(&$errors = null)
    {
        return Smarty_Internal_Utility::testInstall($this, $errors);
    }

    /**
     * Registers plugin to be used in templates
     *
     * @param  string   $type       plugin type
     * @param  string   $tag        name of template tag
     * @param  callback $callback   PHP callback to register
     * @param  bool     $cacheable  if true (default) this fuction is cachable
     * @param  array    $cache_attr caching attributes if any
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     * @throws SmartyException              when the plugin tag is invalid
     */
    public function registerPlugin($type, $tag, $callback, $cacheable = true, $cache_attr = null)
    {
        if (isset($this->registered_plugins[$type][$tag])) {
            throw new SmartyException("Plugin tag \"{$tag}\" already registered");
        } elseif (!is_callable($callback)) {
            throw new SmartyException("Plugin \"{$tag}\" not callable");
        } else {
            $this->registered_plugins[$type][$tag] = array($callback, (bool) $cacheable, (array) $cache_attr);
        }

        return $this;
    }

    /**
     * Unregister Plugin
     *
     * @param  string $type of plugin
     * @param  string $tag  name of plugin
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function unregisterPlugin($type, $tag)
    {
        if (isset($this->registered_plugins[$type][$tag])) {
            unset($this->registered_plugins[$type][$tag]);
        }

        return $this;
    }

    /**
     * Registers a resource to fetch a template
     *
     * @param  string                $type     name of resource type
     * @param  Smarty_Resource|array $callback or instance of Smarty_Resource, or array of callbacks to handle resource
     *                                         (deprecated)
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function registerResource($type, $callback)
    {
        $this->registered_resources[$type] = $callback instanceof Smarty_Resource ? $callback : array($callback, false);

        return $this;
    }

    /**
     * Unregisters a resource
     *
     * @param  string $type name of resource type
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function unregisterResource($type)
    {
        if (isset($this->registered_resources[$type])) {
            unset($this->registered_resources[$type]);
        }

        return $this;
    }

    /**
     * Registers a cache resource to cache a template's output
     *
     * @param  string               $type     name of cache resource type
     * @param  Smarty_CacheResource $callback instance of Smarty_CacheResource to handle output caching
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function registerCacheResource($type, Smarty_CacheResource $callback)
    {
        $this->registered_cache_resources[$type] = $callback;

        return $this;
    }

    /**
     * Unregisters a cache resource
     *
     * @param  string $type name of cache resource type
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function unregisterCacheResource($type)
    {
        if (isset($this->registered_cache_resources[$type])) {
            unset($this->registered_cache_resources[$type]);
        }

        return $this;
    }

    /**
     * Registers object to be used in templates
     *
     * @param          $object_name
     * @param  object  $object_impl   the referenced PHP object to register
     * @param  array   $allowed       list of allowed methods (empty = all)
     * @param  bool    $smarty_args   smarty argument format, else traditional
     * @param  array   $block_methods list of block-methods
     *
     * @throws SmartyException
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function registerObject($object_name, $object_impl, $allowed = array(), $smarty_args = true, $block_methods = array())
    {
        // test if allowed methods callable
        if (!empty($allowed)) {
            foreach ((array) $allowed as $method) {
                if (!is_callable(array($object_impl, $method)) && !property_exists($object_impl, $method)) {
                    throw new SmartyException("Undefined method or property '$method' in registered object");
                }
            }
        }
        // test if block methods callable
        if (!empty($block_methods)) {
            foreach ((array) $block_methods as $method) {
                if (!is_callable(array($object_impl, $method))) {
                    throw new SmartyException("Undefined method '$method' in registered object");
                }
            }
        }
        // register the object
        $this->registered_objects[$object_name] =
            array($object_impl, (array) $allowed, (bool) $smarty_args, (array) $block_methods);

        return $this;
    }

    /**
     * return a reference to a registered object
     *
     * @param  string $name object name
     *
     * @return object
     * @throws SmartyException if no such object is found
     */
    public function getRegisteredObject($name)
    {
        if (!isset($this->registered_objects[$name])) {
            throw new SmartyException("'$name' is not a registered object");
        }
        if (!is_object($this->registered_objects[$name][0])) {
            throw new SmartyException("registered '$name' is not an object");
        }

        return $this->registered_objects[$name][0];
    }

    /**
     * unregister an object
     *
     * @param  string $name object name
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function unregisterObject($name)
    {
        if (isset($this->registered_objects[$name])) {
            unset($this->registered_objects[$name]);
        }

        return $this;
    }

    /**
     * Registers static classes to be used in templates
     *
     * @param         $class_name
     * @param  string $class_impl the referenced PHP class to register
     *
     * @throws SmartyException
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function registerClass($class_name, $class_impl)
    {
        // test if exists
        if (!class_exists($class_impl)) {
            throw new SmartyException("Undefined class '$class_impl' in register template class");
        }
        // register the class
        $this->registered_classes[$class_name] = $class_impl;

        return $this;
    }

    /**
     * Registers a default plugin handler
     *
     * @param  callable $callback class/method name
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     * @throws SmartyException              if $callback is not callable
     */
    public function registerDefaultPluginHandler($callback)
    {
        if (is_callable($callback)) {
            $this->default_plugin_handler_func = $callback;
        } else {
            throw new SmartyException("Default plugin handler '$callback' not callable");
        }

        return $this;
    }

    /**
     * Registers a default template handler
     *
     * @param  callable $callback class/method name
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     * @throws SmartyException              if $callback is not callable
     */
    public function registerDefaultTemplateHandler($callback)
    {
        Smarty_Internal_Extension_DefaultTemplateHandler::registerDefaultTemplateHandler($this, $callback);
        return $this;
    }

    /**
     * Registers a default template handler
     *
     * @param  callable $callback class/method name
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     * @throws SmartyException              if $callback is not callable
     */
    public function registerDefaultConfigHandler($callback)
    {
        Smarty_Internal_Extension_DefaultTemplateHandler::registerDefaultConfigHandler($this, $callback);
        return $this;
    }

    /**
     * Registers a filter function
     *
     * @param  string   $type filter type
     * @param  callback $callback
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function registerFilter($type, $callback)
    {
        $this->registered_filters[$type][$this->_get_filter_name($callback)] = $callback;

        return $this;
    }

    /**
     * Unregisters a filter function
     *
     * @param  string   $type filter type
     * @param  callback $callback
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function unregisterFilter($type, $callback)
    {
        $name = $this->_get_filter_name($callback);
        if (isset($this->registered_filters[$type][$name])) {
            unset($this->registered_filters[$type][$name]);
        }

        return $this;
    }

    /**
     * Return internal filter name
     *
     * @param  callback $function_name
     *
     * @return string   internal filter name
     */
    public function _get_filter_name($function_name)
    {
        if (is_array($function_name)) {
            $_class_name = (is_object($function_name[0]) ?
                get_class($function_name[0]) : $function_name[0]);

            return $_class_name . '_' . $function_name[1];
        } else {
            return $function_name;
        }
    }

    /**
     * load a filter of specified type and name
     *
     * @param  string $type filter type
     * @param  string $name filter name
     *
     * @throws SmartyException if filter could not be loaded
     */
    public function loadFilter($type, $name)
    {
        $_plugin = "smarty_{$type}filter_{$name}";
        $_filter_name = $_plugin;
        if ($this->loadPlugin($_plugin)) {
            if (class_exists($_plugin, false)) {
                $_plugin = array($_plugin, 'execute');
            }
            if (is_callable($_plugin)) {
                $this->registered_filters[$type][$_filter_name] = $_plugin;

                return true;
            }
        }
        throw new SmartyException("{$type}filter \"{$name}\" not callable");
    }

    /**
     * unload a filter of specified type and name
     *
     * @param  string $type filter type
     * @param  string $name filter name
     *
     * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or
     *                                      Smarty_Internal_Template) instance for chaining
     */
    public function unloadFilter($type, $name)
    {
        $_filter_name = "smarty_{$type}filter_{$name}";
        if (isset($this->registered_filters[$type][$_filter_name])) {
            unset ($this->registered_filters[$type][$_filter_name]);
        }

        return $this;
    }

    /**
     * @param bool $compile_check
     */
    public function setCompileCheck($compile_check)
    {
        $this->compile_check = $compile_check;
    }

    /**
     * @param bool $use_sub_dirs
     */
    public function setUseSubDirs($use_sub_dirs)
    {
        $this->use_sub_dirs = $use_sub_dirs;
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
     * @param int $error_reporting
     */
    public function setErrorReporting($error_reporting)
    {
        $this->error_reporting = $error_reporting;
    }

    /**
     * @param bool $escape_html
     */
    public function setEscapeHtml($escape_html)
    {
        $this->escape_html = $escape_html;
    }

    /**
     * @param bool $auto_literal
     */
    public function setAutoLiteral($auto_literal)
    {
        $this->auto_literal = $auto_literal;
    }

    /**
     * @param bool $merge_compiled_includes
     */
    public function setMergeCompiledIncludes($merge_compiled_includes)
    {
        $this->merge_compiled_includes = $merge_compiled_includes;
    }

    /**
     * @param string $left_delimiter
     */
    public function setLeftDelimiter($left_delimiter)
    {
        $this->left_delimiter = $left_delimiter;
    }

    /**
     * @param string $right_delimiter
     */
    public function setRightDelimiter($right_delimiter)
    {
        $this->right_delimiter = $right_delimiter;
    }

    /**
     * @param bool $debugging
     */
    public function setDebugging($debugging)
    {
        $this->debugging = $debugging;
    }

    /**
     * @param bool $config_overwrite
     */
    public function setConfigOverwrite($config_overwrite)
    {
        $this->config_overwrite = $config_overwrite;
    }

    /**
     * @param bool $config_booleanize
     */
    public function setConfigBooleanize($config_booleanize)
    {
        $this->config_booleanize = $config_booleanize;
    }

    /**
     * @param bool $config_read_hidden
     */
    public function setConfigReadHidden($config_read_hidden)
    {
        $this->config_read_hidden = $config_read_hidden;
    }

    /**
     * @param bool $compile_locking
     */
    public function setCompileLocking($compile_locking)
    {
        $this->compile_locking = $compile_locking;
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        // intentionally left blank
    }

    /**
     * Delete template or config variables (save memory)
     */
    public function __clone()
    {
        $this->tpl_vars = array();
        $this->config_vars = array();
    }

    /**
     * <<magic>> Generic getter.
     * Calls the appropriate getter function.
     * Issues an E_USER_NOTICE if no valid getter is found.
     *
     * @param  string $name property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $allowed = array(
            'template_dir' => 'getTemplateDir',
            'config_dir'   => 'getConfigDir',
            'plugins_dir'  => 'getPluginsDir',
            'compile_dir'  => 'getCompileDir',
            'cache_dir'    => 'getCacheDir',
        );

        if (isset($allowed[$name])) {
            return $this->{$allowed[$name]}();
        } else {
            trigger_error('Undefined property: ' . get_class($this) . '::$' . $name, E_USER_NOTICE);
        }
    }

    /**
     * <<magic>> Generic setter.
     * Calls the appropriate setter function.
     * Issues an E_USER_NOTICE if no valid setter is found.
     *
     * @param string $name  property name
     * @param mixed  $value parameter passed to setter
     */
    public function __set($name, $value)
    {
        $allowed = array(
            'template_dir' => 'setTemplateDir',
            'config_dir'   => 'setConfigDir',
            'plugins_dir'  => 'setPluginsDir',
            'compile_dir'  => 'setCompileDir',
            'cache_dir'    => 'setCacheDir',
        );

        if (isset($allowed[$name])) {
            $this->{$allowed[$name]}($value);
        } else {
            trigger_error('Undefined property: ' . get_class($this) . '::$' . $name, E_USER_NOTICE);
        }
    }

    /**
     * preg_replace callback to convert camelcase getter/setter to underscore property names
     *
     * @param  string $match match string
     *
     * @return string replacemant
     */
    public function replaceCamelcase($match)
    {
        return "_" . strtolower($match[1]);
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

        // see if this is a set/get for a property
        $first3 = strtolower(substr($name, 0, 3));
        if (isset($_prefixes[$first3]) && isset($name[3]) && $name[3] !== '_') {
            if (isset($_resolved_property_name[$name])) {
                $property_name = $_resolved_property_name[$name];
            } else {
                // try to keep case correct for future PHP 6.0 case-sensitive class methods
                // lcfirst() not available < PHP 5.3.0, so improvise
                $property_name = strtolower(substr($name, 3, 1)) . substr($name, 4);
                // convert camel case to underscored name
                $property_name = preg_replace_callback('/([A-Z])/', array($this, 'replaceCamelcase'), $property_name);
                if (property_exists($this, $property_name)) {
                    $_resolved_property_name[$name] = $property_name;
                } else {
                    throw new SmartyException("'$name' called for unknown property.");
                }
            }
            if ($first3 == 'get') {
                return $this->$property_name;
            } else {
                return $this->$property_name = $args[0];
            }
        }
        if ($name == 'Smarty') {
            throw new SmartyException("PHP5 requires you to call __construct() instead of Smarty()");
        }
        // must be unknown
        throw new SmartyException("Call of unknown method '$name'.");
    }

    /**
     * Error Handler to mute expected messages
     *
     * @link http://php.net/set_error_handler
     *
     * @param  integer $errno Error level
     * @param          $errstr
     * @param          $errfile
     * @param          $errline
     * @param          $errcontext
     *
     * @return bool
     */
    public static function mutingErrorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $_is_muted_directory = false;

        // add the SMARTY_DIR to the list of muted directories
        if (!isset(Smarty::$_muted_directories[SMARTY_DIR])) {
            $smarty_dir = realpath(SMARTY_DIR);
            if ($smarty_dir !== false) {
                Smarty::$_muted_directories[SMARTY_DIR] = array(
                    'file'   => $smarty_dir,
                    'length' => strlen($smarty_dir),
                );
            }
        }

        // walk the muted directories and test against $errfile
        foreach (Smarty::$_muted_directories as $key => &$dir) {
            if (!$dir) {
                // resolve directory and length for speedy comparisons
                $file = realpath($key);
                if ($file === false) {
                    // this directory does not exist, remove and skip it
                    unset(Smarty::$_muted_directories[$key]);
                    continue;
                }
                $dir = array(
                    'file'   => $file,
                    'length' => strlen($file),
                );
            }
            if (!strncmp($errfile, $dir['file'], $dir['length'])) {
                $_is_muted_directory = true;
                break;
            }
        }

        // pass to next error handler if this error did not occur inside SMARTY_DIR
        // or the error was within smarty but masked to be ignored
        if (!$_is_muted_directory || ($errno && $errno & error_reporting())) {
            if (Smarty::$_previous_error_handler) {
                return call_user_func(Smarty::$_previous_error_handler, $errno, $errstr, $errfile, $errline, $errcontext);
            } else {
                return false;
            }
        }
    }

    /**
     * Enable error handler to mute expected messages
     *
     * @return void
     */
    public static function muteExpectedErrors()
    {
        /*
            error muting is done because some people implemented custom error_handlers using
            http://php.net/set_error_handler and for some reason did not understand the following paragraph:

                It is important to remember that the standard PHP error handler is completely bypassed for the
                error types specified by error_types unless the callback function returns FALSE.
                error_reporting() settings will have no effect and your error handler will be called regardless -
                however you are still able to read the current value of error_reporting and act appropriately.
                Of particular note is that this value will be 0 if the statement that caused the error was
                prepended by the @ error-control operator.

            Smarty deliberately uses @filemtime() over file_exists() and filemtime() in some places. Reasons include
                - @filemtime() is almost twice as fast as using an additional file_exists()
                - between file_exists() and filemtime() a possible race condition is opened,
                  which does not exist using the simple @filemtime() approach.
        */
        $error_handler = array('Smarty', 'mutingErrorHandler');
        $previous = set_error_handler($error_handler);

        // avoid dead loops
        if ($previous !== $error_handler) {
            Smarty::$_previous_error_handler = $previous;
        }
    }

    /**
     * Disable error handler muting expected messages
     *
     * @return void
     */
    public static function unmuteExpectedErrors()
    {
        restore_error_handler();
    }
}
