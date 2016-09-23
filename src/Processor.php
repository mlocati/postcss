<?php

namespace PostCSS;

use PostCSS\Plugin\PluginInterface;

/**
 * Contains plugins to process CSS. Create one `Processor` instance, initialize its plugins, and then use that instance on numerous CSS files.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/processor.es6
 * @link https://github.com/postcss/postcss/blob/master/lib/postcss.es6
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
     * @param PluginInterface[]|callable[]|Processor[]} $plugins PostCSS plugins
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
     * PostCSS plugin can be in 4 formats:
     * * A plugin created by {@link postcss.plugin} method.
     * * A function. PostCSS will pass the function a {@link Root}
     *   as the first argument and current {@link Result} instance
     *   as the second.
     * * An object with a `postcss` method. PostCSS will use that method
     *   as described in #2.
     * * Another {@link Processor} instance. PostCSS will copy plugins
     *   from that instance into this one.
     *
     * Plugins can also be added by passing them as arguments when creating
     * a `postcss` instance (see [`postcss(plugins)`]).
     *
     * Asynchronous plugins should return a `\React\Promise\Promise` instance.
     *
     * @param {Plugin|pluginFunction|Processor} plugin - PostCSS plugin
     *                                                   or {@link Processor}
     *                                                   with plugins
     *
     * @example
     * const processor = postcss()
     *   .use(autoprefixer)
     *   .use(precss);
     *
     * @return {Processes} current processor to make methods chain
     */
    public function usePlugin($plugin)
    {
        $this->plugins = array_merge($this->plugins, static::normalize([$plugin]));

        return $this;
    }

    /**
     * Parses source CSS and returns a {@link LazyResult} Promise proxy.
     * Because some plugins can be asynchronous it doesn't make
     * any transformations. Transformations will be applied
     * in the {@link LazyResult} methods.
     *
     * @param {string|toString|Result} css - String with input CSS or
     *                                       any object with a `toString()`
     *                                       method, like a Buffer.
     *                                       Optionally, send a {@link Result}
     *                                       instance and the processor will
     *                                       take the {@link Root} from it
     * @param {processOptions} [opts]      - options
     *
     * @return LazyResult \React\Promise\Promise proxy
     *
     * @example
     * processor.process(css, { from: 'a.css', to: 'a.out.css' })
     *   .then(result => {
     *      console.log(result.css);
     *   });
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
