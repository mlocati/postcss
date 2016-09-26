<?php

namespace PostCSS;

use PostCSS\Plugin\PluginInterface;

/**
 * Contains plugins to process CSS. Create one `Processor` instance, initialize its plugins, and then use that instance on numerous CSS files.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/processor.es6
 * @link https://github.com/postcss/postcss/blob/master/lib/postcss.es6
 *
 * @example
 * $processor = new \PostCSS\Processor([Autoprefixer::class, Precss::class]);
 * $processor->process($css1)->then(function ($result) { echo $result->css; })->done();
 * $processor->process($css2)->then(function ($result) { echo $result->css; })->done();
 */
class Processor
{
    /**
     * Current PostCSS version.
     *
     * @var string
     */
    const VERSION = '5.2.0';

    /**
     * @var array
     */
    public $plugins;

    /**
     * @param PluginInterface[]|callable[]|Processor[]} $plugins PostCSS plugins. Processor->usePlugin for plugin format
     */
    public function __construct($plugins = [])
    {
        if (func_num_args() > 1) {
            $plugins = func_get_args();
        } elseif (!is_array($plugins)) {
            $plugins = [$plugins];
        }
        $this->plugins = static::normalize($plugins);
    }

    /**
     * Adds a plugin to be used as a CSS processor.
     *
     * PostCSS plugin can be in the following formats:
     * - An \PostCSS\PluginInterface instance.
     * - The name of a \PostCSS\PluginInterface class.
     * - A callable that returns a \PostCSS\PluginInterface instance.
     * - Another Processor} instance. PostCSS will copy plugins from that instance into this one.
     *
     * Plugins can also be added by passing them as arguments when creating \PostCSS\Processor instance.
     *
     * Asynchronous plugins should return a `\React\Promise\Promise` instance.
     *
     * @param PluginInterface|string|callable|Processor $plugin PostCSS plugin Processor with plugins
     *
     * @example
     * $processor = (new \PostCSS\Processor())
     *     ->usePlugin(Autoprefixer::class)
     *     ->usePlugin(Precss::class);
     *
     * @return static Current processor to make methods chain
     */
    public function usePlugin($plugin)
    {
        $this->plugins = array_merge($this->plugins, static::normalize([$plugin]));

        return $this;
    }

    /**
     * Parses source CSS and returns a LazyResult Promise proxy.
     * Because some plugins can be asynchronous it doesn't make any transformations.
     * Transformations will be applied in the LazyResult methods.
     *
     * @param string|Root|Result|LazyResult $css String with input CSS or any object with a `__toString()` method. Optionally, send a Result instance and the processor will take the Root from it
     * @param array $opts] Options
     *
     * @return LazyResult \React\Promise\Promise proxy
     *
     * @example
     * $processor->process($css, ['from' => 'a.css', 'to' => 'a.out.css'])->then(function ($result) { echo $result->css; })->done();
     */
    public function process($css, array $opts = [])
    {
        return new LazyResult($this, $css, $opts);
    }

    private static function normalize(array $plugins)
    {
        $normalized = [];
        foreach ($plugins as $plugin) {
            if (is_string($plugin) && class_exists($plugin, true)) {
                $plugin = new $plugin();
            }
            if (is_callable($plugin)) {
                $plugin = call_user_func($plugin);
            }
            if (is_object($plugin) && isset($plugin->postcss)) {
                $normalized = array_merge($normalized, self::normalize([$plugin->postcss]));
            } elseif (is_array($plugin) && isset($plugin['postcss'])) {
                $normalized = array_merge($normalized, self::normalize([$plugin['postcss']]));
            } elseif ($plugin instanceof PluginInterface) {
                $normalized[] = $plugin;
            } elseif ($plugin instanceof self) {
                $normalized = array_merge($normalized, $plugin->plugins);
            } else {
                throw new \Exception(json_encode($plugin).' is not a PostCSS plugin');
            }
        }

        return $normalized;
    }
}
