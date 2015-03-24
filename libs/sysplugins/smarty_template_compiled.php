<?php

/**
 * Smarty Resource Data Object
 * Meta Data Container for Template Files
 *
 * @package    Smarty
 * @subpackage TemplateResources
 * @author     Rodney Rehm
 * @property string $content compiled content
 */
class Smarty_Template_Compiled
{
    /**
     * Compiled Filepath
     *
     * @var string
     */
    public $filepath = null;

    /**
     * Compiled Timestamp
     *
     * @var integer
     */
    public $timestamp = null;

    /**
     * Compiled Existence
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Compiled Content Loaded
     *
     * @var bool
     */
    public $processed = false;

    public $cacheKey = null;

    /**
     * Code of recompiled template resource
     *
     * @var string|null
     */
    public $code = null;

    /**
     * @var null| string
     */
    public $compiledClass = null;

    /**
     * @var null|
     */
    public $compiledTplObj = null;

    /**
     * create Compiled Object container
     */
    public function __construct($cacheKey)
    {
        $this->cacheKey = $cacheKey;
    }

    /**
     * get a Compiled Object of this source
     *
     * @param  Smarty_Internal_Template $_template template object
     *
     * @return Smarty_Template_Compiled compiled object
     */
    static function load($_template)
    {
        if (!isset($_template->source)) {
            $_template->source = Smarty_Template_Source::load($_template);
        }
        $src = $_template->source;
        $_cache_key = null;
        // check runtime cache
        if ($crc = (!$src->recompiled && $_template->smarty->resource_caching)) {
            $_cache_key = $src->unique_resource . '#';
            if ($_template->caching) {
                $_cache_key .= 'caching#';
            }
            $_cache_key .= $_template->compile_id;
            if (isset($src->compileds[$_cache_key])) {
                return $src->compileds[$_cache_key];
            }
        }
        $compiled = new Smarty_Template_Compiled($_cache_key);
        if (method_exists($src->handler, 'populateCompiledFilepath')) {
            $src->handler->populateCompiledFilepath($compiled, $_template);
        } else {
            $compiled->populateCompiledFilepath($_template);
        }
        // runtime cache
        if ($crc) {
            $src->compileds[$_cache_key] = $compiled;
        }
        return $compiled;
    }

    /**
     * populate Compiled Object with compiled filepath
     *
     * @param Smarty_Internal_Template $_template template object
     **/
    public function populateCompiledFilepath(Smarty_Internal_Template $_template)
    {
        $_compile_id = isset($_template->compile_id) ? preg_replace('![^\w\|]+!', '_', $_template->compile_id) : null;
        if ($_template->source->isConfig) {
            $_flag = '_' . ((int) $_template->smarty->config_read_hidden + (int) $_template->smarty->config_booleanize * 2
                    + (int) $_template->smarty->config_overwrite * 4);
        } else {
            $_flag = '_' . ((int) $_template->smarty->merge_compiled_includes + (int) $_template->smarty->escape_html * 2);
        }
        $_filepath = $_template->source->uid . $_flag;
        // if use_sub_dirs, break file into directories
        if ($_template->smarty->use_sub_dirs) {
            $_filepath = substr($_filepath, 0, 2) . DS
                . substr($_filepath, 2, 2) . DS
                . substr($_filepath, 4, 2) . DS
                . $_filepath;
        }
        $_compile_dir_sep = $_template->smarty->use_sub_dirs ? DS : '^';
        if (isset($_compile_id)) {
            $_filepath = $_compile_id . $_compile_dir_sep . $_filepath;
        }
        // caching token
        if ($_template->caching) {
            $_cache = '.cache';
        } else {
            $_cache = '';
        }
        $_compile_dir = $_template->smarty->getCompileDir();
        // set basename if not specified
        $_basename = $_template->source->handler->getBasename($_template->source);
        if ($_basename === null) {
            $_basename = basename(preg_replace('![^\w\/]+!', '_', $_template->source->name));
        }
        // separate (optional) basename by dot
        if ($_basename) {
            $_basename = '.' . $_basename;
        }

        $this->filepath = $_compile_dir . $_filepath . '.' . $_template->source->type . $_basename . $_cache . '.php';
        if (is_file($this->filepath)) {
            $this->timestamp = filemtime($this->filepath);
            $this->exists = true;
        } else {
            $this->exists = false;
        }
    }

    /**
     * load compiled template or compile from source
     *
     * @param Smarty_Internal_Template $_template
     *
     * @throws Exception
     */
    public function process(Smarty_Internal_Template $_template)
    {
        if ($_template->source->recompiled || !$_template->compiled->exists || $_template->smarty->force_compile) {
            $this->compiledClass = $this->compiledTplObj = null;
            $this->compileTemplateSource($_template);
            $compileCheck = $_template->smarty->compile_check;
            $_template->smarty->compile_check = false;
            if ($_template->source->recompiled) {
                $level = ob_get_level();
                try {
                    eval("?>" . $this->code);
                    if (class_exists($this->compiledClass, false)) {
                        $this->compiledTplObj = new $this->compiledClass($_template);
                    }
                }
                catch (Exception $e) {
                    while (ob_get_level() > $level) {
                        ob_end_clean();
                    }
                    throw $e;
                }
                $this->code = null;
            } else {
                include($_template->compiled->filepath);
                if (class_exists($this->compiledClass, false)) {
                    $this->compiledTplObj = new $this->compiledClass($_template);
                }
            }
            $_template->smarty->compile_check = $compileCheck;
        } else {
            include($_template->compiled->filepath);
            if (class_exists($this->compiledClass, false)) {
                $this->compiledTplObj = new $this->compiledClass($_template);
            }
            if ($_template->mustCompile) {
                $this->compiledClass = $this->compiledTplObj = null;
                $this->compileTemplateSource($_template);
                $compileCheck = $_template->smarty->compile_check;
                $_template->smarty->compile_check = false;
                include($_template->compiled->filepath);
                if (class_exists($this->compiledClass, false)) {
                    $this->compiledTplObj = new $this->compiledClass($_template);
                }
                $_template->smarty->compile_check = $compileCheck;
            }
        }
        $this->processed = true;
    }

    /**
     * render compiled template code
     *
     * @param Smarty_Internal_Template $_template
     *
     * @return string
     * @throws Exception
     */
    public function render(Smarty_Internal_Template $_template)
    {

        if (!$this->processed) {
            $this->process($_template);
        }
        $this->compiledTplObj->getRenderedTemplateCode($_template);
        return;
    }

    /**
     * compile template from source
     *
     * @param Smarty_Internal_Template $_template
     *
     * @return string
     * @throws Exception
     */
    public function compileTemplateSource(Smarty_Internal_Template $_template)
    {
        // compile locking
        if (!$_template->source->recompiled) {
            if ($saved_timestamp = $_template->compiled->timestamp) {
                touch($_template->compiled->filepath);
            }
        }
        $level = ob_get_level();
        try {
            $_template->loadCompiler();
            $code = $_template->compiler->compileTemplate($_template);
        }
        catch (Exception $e) {
            $_template->context = null;
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            // restore old timestamp in case of error
            if (!$_template->source->recompiled && $saved_timestamp) {
                touch($_template->compiled->filepath, $saved_timestamp);
            }
            throw $e;
        }
        // compiling succeeded
        if ($_template->compiler->write_compiled_code) {
            // write compiled template
            $this->write($_template, $code);
            $code = '';
        }
        // release compiler object to free memory
        unset($_template->compiler);
        return $code;
    }

    /**
     * Write compiled code by handler
     *
     * @param Smarty_Internal_Template $_template template object
     * @param string                   $code      compiled code
     *
     * @return bool success
     */
    public function write(Smarty_Internal_Template $_template, $code)
    {
        if (!$_template->source->recompiled) {
            if (Smarty_Internal_Write_File::writeFile($this->filepath, $code, $_template->smarty) === true) {
                if (is_file($this->filepath)) {
                    $this->timestamp = filemtime($this->filepath);
                    $this->exists = true;
                    return true;
                }
            }
            $this->exists = false;
            return false;
        } else {
            $this->code = $code;
            $this->timestamp = time();
            $this->exists = true;
            return true;
        }
    }

    /**
     * Read compiled content from handler
     *
     * @param Smarty_Internal_Template $_template template object
     *
     * @return string content
     */
    public function read(Smarty_Internal_Template $_template)
    {
        if (!$_template->source->recompiled) {
            return file_get_contents($this->filepath);
        }
        return isset($this->content) ? $this->content : false;
    }
}
