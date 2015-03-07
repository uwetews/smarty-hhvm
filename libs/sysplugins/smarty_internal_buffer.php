<?php
/**
 * Smarty Plugin Buffer
 * This file contains the buffer object
 *
 * @package    Smarty
 * @subpackage Template
 * @author     Uwe Tews
 */

/**
 * class for the Smarty Buffer object
 * The Smarty buffer object will collect compiled template or cache code and raw text content
 *
 * @package    Smarty
 * @subpackage Template
 */
class Smarty_Internal_Buffer
{

    /**
     * Code/Text Buffer
     *
     * @var string
     */
    public $buffer = '';

    /**
     * flag if buffer is used for cache file
     *
     * @var bool
     */
    private $caching = false;

    /**
     * Flag if output buffering is active
     *
     * @var bool
     */
    private $obActive = false;

    /**
     * Flag if buffer contains nocache code
     *
     * @var bool
     */
    public $hasNocacheCode = false;

    /**
     * Saved ob level
     *
     * @var int
     */
    private $ob_level = 0;

    /**
     * Constructor
     *
     * @param bool $caching caching mode
     * @param bool $obStart flag if output burring shall be started
     */
    public function  __construct($caching = false, $obStart = true)
    {
        $this->caching = $caching;
        if ($obStart) {
            $this->ob_level = ob_get_level();
            ob_start();
            $this->obActive = true;
        }
    }

    /**
     * Flush output buffer and append nocache code
     *
     * @param string $code add raw text or nocache code to buffer
     */
    public function toBufferCacheCode($code)
    {
        if (!empty($code)) {
            if (ob_get_length()) {
                $this->buffer .= "echo '" . addcslashes(ob_get_clean(), '\'\\') . "';\n";
                ob_start();
            }
            $this->hasNocacheCode = true;
            $this->buffer .= $code;
        }
    }

    /**
     * Start output buffering if not already active
     */
    public function startBuffer()
    {
        if (!$this->obActive) {
            $this->ob_level = ob_get_level();
            ob_start();
            $this->obActive = true;
        }
    }

    /**
     * Stop output buffering
     */
    public function endBuffer()
    {
        if ($this->obActive) {
            if (false !== $code = ob_get_clean()) {
                if ($this->caching) {
                    $this->buffer .= "echo '" . addcslashes($code, '\'\\') . "';\n";
                } else {
                    $this->buffer .= $code;
                }
            }
            $this->obActive = false;
        }
    }

    /**
     * Flush output buffering
     */
    public function flushBuffer($restart = true)
    {
        if ($this->obActive && ob_get_length()) {
            $code = ob_get_clean();
            if ($this->caching) {
                $this->buffer .= "echo '" . addcslashes($code, '\'\\') . "';\n";
            } else {
                $this->buffer .= $code;
            }
            if ($restart) {
                ob_start();
            }
        }
    }

    /**
     * Stop output buffering and return buffer content
     *
     * @return string
     */
    public function getRawBuffer()
    {
        $this->endBuffer();
        return $this->buffer;
    }

    /**
     * On destruct make sure buffer level is reset
     */
    public function __destruct()
    {
        while (ob_get_level() > $this->ob_level) {
            ob_end_clean();
        }
    }
}