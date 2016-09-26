<?php

namespace PostCSS;

/**
 * Represents a plugin's warning. It can be created using Node->warn.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/warning.es6
 *
 * @example
 * if ($decl->important ) {
 *     $decl->warn($result, 'Avoid !important', ['word' => '!important']);
 * }
 */
class Warning
{
    /**
     * The warning message.
     *
     * @var string
     */
    public $text;

    /**
     * Line in the input file with this warning's source.
     *
     * @var int|null
     */
    public $line = null;

    /**
     * Column in the input file with this warning's source.
     *
     * @var int|null
     */
    public $column = null;

    /**
     * Contains the CSS node that caused the warning.
     *
     * @var Node|null
     */
    public $node = null;

    /**
     * The name of the plugin that created it will fill this property automatically when you call Node->warn.
     *
     * @var string|null
     */
    public $plugin = null;

    /**
     * Index in CSS node string that caused the warning.
     *
     * @var int|null
     */
    public $index = null;

    /**
     * Word in CSS source that caused the warning.
     *
     * @var string|null
     */
    public $word = null;

    /**
     * @param string $text Warning message
     * @param array $opts Warning options. {
     *
     *     @var Node $node CSS node that caused the warning
     *     @var string $word Word in CSS source that caused the warning
     *     @var int $index Index in CSS node string that caused the warning
     *     @var string $plugin Name of the plugin that created this warning. Result->warn fills this property automatically.
     * }
     */
    public function __construct($text, array $opts = [])
    {
        $this->text = (string) $text;
        if (isset($opts['node']) && isset($opts['node']->source)) {
            $pos = $opts['node']->positionBy($opts);
            $this->line = $pos['line'];
            $this->column = $pos['column'];
        }
        foreach ($opts as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * Returns a warning position and message.
     *
     * @return string
     */
    public function __toString()
    {
        if (isset($this->node)) {
            return $this->node->error($this->text, [
                'plugin' => $this->plugin,
                'index' => $this->index,
                'word' => $this->word,
            ])->getMessage();
        } elseif ($this->plugin) {
            return ((string) $this->plugin).': '.$this->text;
        } else {
            return $this->text;
        }
    }
}
