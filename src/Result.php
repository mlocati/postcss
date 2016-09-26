<?php

namespace PostCSS;

use PostCSS\Plugin\PluginInterface;

/**
 * Provides the result of the PostCSS transformations.
 * A Result instance is returned by LazyResult->then or Root->toResult methods.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/result.es6
 *
 * @example
 * (new \PostCSS\Processor([cssnext]))->process($css)->then(function ($result) {
 *    echo $result->css;
 * });
 * @example
 * $result2 = \PostCSS\Parser::parse($css).toResult();
 *
 * @property string $content An alias for the Result->css property. Use it with syntaxes that generate non-CSS output
 */
class Result
{
    /**
     * The Processor instance used for this transformation.
     *
     * @var Processor
     */
    public $processor;

    /**
     * Contains messages from plugins (e.g., warnings or custom messages). Each message should have type and plugin properties.
     *
     * @var array
     */
    public $messages;

    /**
     * Root node after all transformations.
     *
     * @example
     * $root->toResult()->root === root;
     *
     * @var Root|null
     */
    public $root;

    /**
     * Options from the Processor->process or Root->toResult} call that produced this Result instance.
     *
     * @example
     * $root->toResult($opts)->opts == $opts;
     *
     * @var array
     */
    public $opts;

    /**
     * A CSS string representing of Result->root.
     *
     * @example
     * \PostCSS\Parser::parse('a{}')->toResult()->css //=> 'a{}'
     *
     * @var string|null
     */
    public $css;

    /**
     * An instance of `SourceMapGenerator` class from the `source-map` library, representing changes o the Result->root instance.
     *
     * @example
     * $result->map->toJSON() //=> ['version' => 3, 'file' => 'a.css', ...]
     * @example
     * if ($result->map) {
     *     file_put_contents($result->opts['to'].'.map', (string) $result->map);
     * }
     *
     * @var SourceMap\Generator|null
     */
    public $map;

    /**
     * @var PluginInterface|null
     */
    public $lastPlugin = null;

    /**
     * @param Processor $processor Processor used for this transformation
     * @param Root $root Root node after all transformations
     * @param array $opts Options from the Processor->process or Root->toResult
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
     * Returns for Result->css content.
     *
     * @return string string representing of Result->root
     */
    public function __toString()
    {
        return (string) $this->css;
    }

    /**
     * Creates an instance of {@link Warning} and adds it to Result->messages.
     *
     * @param string $text Warning message
     * @param array $opts Warning options {
     *
     *     @var Node $node CSS node that caused the warning
     *     @var string $word Word in CSS source that caused the warning
     *     @var int $index Index in CSS node string that caused the warning
     *     @var string $plugin Name of the plugin that created this warning. Result->warn fills this property automatically.
     * }
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
     * Returns warnings from plugins. Filters Warning instances from Result->messages.
     *
     * @example
     * foreach ($result->warnings() as warn) {
     *     echo (string) $warn;
     * }
     *
     * @return Warning[] warnings from plugins
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
