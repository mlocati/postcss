<?php

namespace PostCSS;

use PostCSS\Plugin\PluginInterface;

/**
 * Provides the result of the PostCSS transformations.
 * A Result instance is returned by {@link LazyResult#then} or {@link Root#toResult} methods.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/result.es6
 *
 * @example
 * postcss([cssnext]).process(css).then(function (result) {
 *    console.log(result.css);
 * });
 * @example
 * var result2 = postcss.parse(css).toResult();
 *
 * @property string $content An alias for the Result->css property. Use it with syntaxes that generate non-CSS output
 */
class Result
{
    /**
     * @var Processor
     */
    public $processor;

    /**
     * @var array
     */
    public $messages;

    /**
     * @var Root|null
     */
    public $root;

    /**
     * @var array
     */
    public $opts;

    /**
     * @var string|null A CSS string representing of {@link Result#root}
     */
    public $css;

    /**
     * @var SourceMap\Generator|null An instance of `SourceMapGenerator` class from the `source-map` library, representing changes o the {@link Result#root} instance
     */
    public $map;

    /**
     * @var PluginInterface|null
     */
    public $lastPlugin = null;

    /**
     * @param {Processor} processor - processor used for this transformation
     * @param {Root}      root      - Root node after all transformations
     * @param {processOptions} opts - options from the {@link Processor#process}
     *                                or {@link Root#toResult}
     */
    public function __construct(Processor $processor = null, Root $root = null, array $opts = [])
    {
        $this->processor = $processor;
        $this->messages = [];
        $this->root = $root;
        $this->opts = $opts;
        $this->css = null;
        $this->map = null;
    }

    /**
     * Returns for {@link Result#css} content.
     *
     * @return string string representing of {@link Result#root}
     */
    public function __toString()
    {
        return (string) $this->css;
    }

    /**
     * Creates an instance of {@link Warning} and adds it to {@link Result#messages}.
     *
     * @param string $text - warning message
     * @param array  $opts - warning options
     * @param Node   opts.node   - CSS node that caused the warning
     * @param string opts.word   - word in CSS source that caused the warning
     * @param int opts.index  - index in CSS node string that caused the warning
     * @param string opts.plugin - name of the plugin that created this warning. {@link Result#warn} fills this property automatically
     *
     * @return Warning created warning
     */
    public function warn($text, array $opts = [])
    {
        if (!isset($opts['plugin'])) {
            if ($this->lastPlugin !== null) {
                $opts['plugin'] = $this->lastPlugin->getName();
            }
        }

        $warning = new Warning($text, $opts);
        $this->messages[] = $warning;

        return $warning;
    }

    /**
     * Returns warnings from plugins. Filters {@link Warning} instances from {@link Result#messages}.
     *
     * @return {Warning[]} warnings from plugins
     */
    public function warnings()
    {
        return array_values(array_filter($this->messages, function ($i) {
            return $i instanceof Warning;
        }));
    }

    public function __get($name)
    {
        switch ($name) {
            case 'content':
                return $this->css;
            default:
                throw new Exception\UndefinedProperty($this, $name);
        }
    }
}
