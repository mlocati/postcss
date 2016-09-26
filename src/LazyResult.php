<?php

namespace PostCSS;

use PostCSS\Plugin\PluginInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * A Promise proxy for the result of PostCSS transformations.
 *
 * A `LazyResult` instance is returned by Processor->process
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/lazy-result.es6
 *
 * @example
 * $lazy = (new Processor([cssnext]))->progess($css);
 *
 * @property string $css Processes input CSS through synchronous plugins, converts `Root` to a CSS string and returns Result->css. This property will only work with synchronous plugins. If the processor contains any asynchronous plugins it will throw an error. This is why this method is only for debug purpose, you should always use LazyResult->then
 * @property null|SourceMap\Generator $map Processes input CSS through synchronous plugins and returns Result->map. This property will only work with synchronous plugins. If the processor contains any asynchronous plugins it will throw an error. This is why this method is only for debug purpose, you should always use LazyResult->then
 * @property Root $root Processes input CSS through synchronous plugins and returns Result->root. This property will only work with synchronous plugins. If the processor contains any asynchronous plugins it will throw an error. This is why this method is only for debug purpose, you should always use LazyResult->then
 * @property string $content An alias for the `css` property. Use it with syntaxes that generate non-CSS output. This property will only work with synchronous plugins. If the processor contains any asynchronous plugins it will throw an error. This is why this method is only for debug purpose, you should always use LazyResult->then
 * @property mixed[] $messages Processes input CSS through synchronous plugins and returns Result->messages. This property will only work with synchronous plugins. If the processor contains any asynchronous plugins it will throw an error. This is why this method is only for debug purpose, you should always use LazyResult->then
 * @property Processor $processor Returns a Processor instance, which will be used for CSS transformations
 * @property array opts Options from the Processor->process} call
 */
class LazyResult
{
    /**
     * @var bool
     */
    protected $stringified;

    /**
     * @var bool
     */
    protected $processed;

    /**
     * @var \Exception
     */
    protected $error = null;

    /**
     * @var Result
     */
    protected $result;

    /**
     * @var Promise|null
     */
    protected $processing = null;

    /**
     * Current plugin index.
     *
     * @var int
     */
    protected $plugin;

    /**
     * @param Processor $processor
     * @param mixed $css
     * @param array $opts
     */
    public function __construct(Processor $processor, $css, array $opts = [])
    {
        $this->stringified = false;
        $this->processed = false;

        $root = null;
        if ($css instanceof Root) {
            $root = $css;
        } elseif ($css instanceof self || $css instanceof Result) {
            $root = $css->root;
            $map = $css->map;
            if ($map !== null) {
                if (!isset($opts['map'])) {
                    $opts['map'] = [];
                }
                if (!isset($opts['map']['inline'])) {
                    $opts['map']['inline'] = false;
                }
                $opts['map']['prev'] = $map;
            }
        } else {
            $parser = null;
            if (isset($opts['syntax']) && isset($opts['syntax']['parse'])) {
                $parser = $opts['syntax']['parse'];
            }
            if (isset($opts['parser'])) {
                $parser = $opts['parser'];
            }
            if (is_array($parser) && isset($parser['parse'])) {
                $parser = $parser['parse'];
            }
            if ($parser === null) {
                class_exists(Parser::class, true);
                $parser = [Parser::class, 'parse'];
            }
            if (!is_callable($parser)) {
                $this->error = new Exception\CssSyntaxError('Invalid parser supplied');
            } else {
                try {
                    $root = call_user_func($parser, $css, $opts);
                } catch (\Exception $error) {
                    $this->error = $error;
                }
            }
        }

        $this->result = new Result($processor, $root, $opts);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'css':
                return $this->stringify()->css;
            case 'map':
                return $this->stringify()->map;
            case 'root':
                return $this->sync()->root;
            case 'content':
                return $this->stringify()->content;
            case 'processor':
                return $this->result->processor;
            case 'messages':
                return $this->sync()->messages;
            case 'opts':
                return $this->result->opts;
            default:
                throw new Exception\UndefinedProperty($this, $name);
        }
    }

    /**
     * Processes input CSS through synchronous plugins and calls Result->warnings().
     *
     * @return Warning[] warnings from plugins
     */
    public function warnings()
    {
        return $this->sync()->warnings();
    }

    /**
     * Alias for the LazyResult->css property.
     *
     * @example
     * (string) $lazy === $lazy->css;
     *
     * @return string output CSS
     */
    public function __toString()
    {
        return $this->css;
    }

    /**
     * Processes input CSS through synchronous and asynchronous plugins and calls `onFulfilled` with a Result instance.
     * If a plugin throws an error, the `onRejected` callback will be executed.
     *
     * It implements standard Promise API.
     *
     * @param callable $onFulfilled Callback will be executed when all plugins will finish work
     * @param callable $onRejected Callback will be executed on any error
     *
     * @return Promise Promise API to make queue
     *
     * @example
     * (new \PostCSS\Processor([Cssnext::class]))->process($css)->then(function ($result) { echo $result->css; })->done();
     */
    public function then($onFulfilled, $onRejected)
    {
        return $this->async()->then($onFulfilled, $onRejected);
    }

    /**
     * Processes input CSS through synchronous and asynchronous plugins and calls onRejected for each error thrown in any plugin.
     * It implements standard Promise API.
     *
     * @param callable $onRejected Callback will be executed on any error
     *
     * @return Promise Promise API to make queue
     *
     * @example
     * (new \PostCSS\Processor([Cssnext::class]))->process($css)->then(function ($result) {
     *   echo $result->css);
     * })->catchError(function ($error) {
     *   echo (string) $error;
     * });
     */
    public function catchError($onRejected)
    {
        return $this->async()->then(
            null,
            $onRejected
        );
    }

    /**
     * @param \Exception $error
     * @param PluginInterface|string $plugin
     */
    public function handleError(\Exception $error, $plugin)
    {
        try {
            $this->error = $error;
            if ($error instanceof Exception\CssSyntaxError && $error->getPostCSSPlugin() === '') {
                $error->setPostCSSPlugin($plugin);
                $error->updateMessage();
            } elseif ($plugin instanceof PluginInterface && $plugin::POSTCSS_VERSION) {
                $pluginName = $plugin->getName();
                $pluginVer = $plugin->POSTCSS_VERSION;
                $runtimeVer = $this->result->processor->version;
                $a = explode('.', $pluginVer);
                $b = explode('.', $runtimeVer);

                if ($a[0] !== $b[0] || ((int) $a[1]) > ((int) $b[1])) {
                    trigger_error("Your current PostCSS version is $runtimeVer, but $pluginName uses $pluginVer. Perhaps this is the source of the error below.", E_USER_NOTICE);
                }
            }
        } catch (\Exception $err) {
            trigger_error($err->getMessage(), E_USER_NOTICE);
        }
    }

    /**
     * @param callable $resolve
     * @param callable $reject
     *
     * @return mixed
     */
    public function asyncTick($resolve, $reject)
    {
        if ($this->plugin >= count($this->result->processor->plugins)) {
            $this->processed = true;

            return $resolve();
        }
        try {
            $plugin = $this->result->processor->plugins[$this->plugin];
            $promise = $this->run($plugin);
            $this->plugin += 1;

            if ($promise instanceof PromiseInterface) {
                $me = $this;
                $promise->then(
                    function () use ($me, $resolve, $reject) {
                        $me->asyncTick($resolve, $reject);
                    },
                    function ($error) use ($me, $plugin, $reject) {
                        $me->handleError($error, $plugin);
                        $me->processed = true;
                        $reject($error);
                    }
                );
            } else {
                $this->asyncTick($resolve, $reject);
            }
        } catch (\Exception $error) {
            $this->processed = true;
            $reject($error);
        }
    }

    /**
     * @return Promise
     */
    public function async()
    {
        if ($this->processed) {
            $me = $this;

            return new Promise(function ($resolve, $reject) use ($me) {
                if ($me->error) {
                    $reject($me->error);
                } else {
                    $resolve($me->stringify());
                }
            });
        }
        if ($this->processing) {
            return $this->processing;
        }

        $me = $this;
        $this->processing = (new Promise(function ($resolve, $reject) use ($me) {
            if ($me->error) {
                return $reject($me->error);
            }
            $me->plugin = 0;
            $me->asyncTick($resolve, $reject);
        }))->then(
            function () use ($me) {
                $me->processed = true;

                return $me->stringify();
            }
        );

        return $this->processing;
    }

    /**
     * @throws \Exception
     *
     * @return \PostCSS\Result
     */
    public function sync()
    {
        if ($this->processed) {
            return $this->result;
        }
        $this->processed = true;

        if ($this->processing) {
            throw new \Exception('Use process($css)->then($cb) to work with async plugins');
        }

        if ($this->error) {
            throw $this->error;
        }

        $processor = $this->result->processor;
        for ($i = 0; $i < count($processor->plugins); ++$i) {
            $promise = $this->run($processor->plugins[$i]);
            if ($promise instanceof Promise) {
                throw new \Exception('Use process($css)->then($cb) to work with async plugins');
            }
        }

        return $this->result;
    }

    /**
     * @param PluginInterface $plugin
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function run(PluginInterface $plugin)
    {
        $this->result->lastPlugin = $plugin;

        try {
            return $plugin->run($this->result->root, $this->result);
        } catch (\Exception $error) {
            $this->handleError($error, $plugin);
            throw $error;
        }
    }

    /**
     * @return \PostCSS\Result
     */
    public function stringify()
    {
        if ($this->stringified) {
            return $this->result;
        }
        $this->stringified = true;

        $this->sync();

        $opts = $this->result->opts;
        $str = null;
        if (isset($opts['syntax']) && isset($opts['syntax']['stringify'])) {
            $str = $opts['syntax']['stringify'];
        }
        if (isset($opts['stringifier'])) {
            $str = $opts['stringifier'];
        }
        if (is_array($str) && isset($str['stringify'])) {
            $str = $str['stringify'];
        }
        if ($str === null) {
            $str = function (Node $node, callable $builder) {
                $sr = new Stringifier($builder);

                return $sr->stringify($node);
            };
        }

        $map = new MapGenerator($str, $this->result->root, $this->result->opts);
        $data = $map->generate();
        $this->result->css = $data[0];
        $this->result->map = isset($data[1]) ? $data[1] : null;

        return $this->result;
    }
}
