<?php

namespace PostCSS\Plugin;

use PostCSS\Result;
use PostCSS\Root;
use PostCSS\Processor;

class ClosurePlugin implements PluginInterface
{
    protected static $unnamedCount = 0;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var callable
     */
    protected $callable;

    /**
     * @var array
     */
    protected $opts;

    /**
     * @param callable $callable
     * @param string $name
     */
    public function __construct(callable $callable, $name = null)
    {
        $this->callable = $callable;
        if ($name === null) {
            $this->name = null;
            if (is_array($name) && count($name) === 2 && is_string($name[1])) {
                if (is_string($name[0])) {
                    $this->name = implode('::', $name);
                } elseif (is_object($name[0])) {
                    $this->name = get_class($name[0]).'->'.$name[1];
                }
            }
            if ($this->name === null) {
                $this->name = 'Closure #'.++static::$unnamedCount;
            }
        } else {
            $this->name = $name;
        }

        $this->opts = [];
    }

    /**
     * {@inheritdoc}
     *
     * @see PluginInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     *
     * @see PluginInterface::setOptions()
     */
    public function setOptions(array $opts)
    {
        $this->opts = $opts;
    }

    /**
     * {@inheritdoc}
     *
     * @see PluginInterface::run()
     */
    public function run(Root $root, Result $result)
    {
        return call_user_func($this->callable, $root, $result);
    }

    public function process($css, array $unused = [])
    {
        return (new Processor([$this]))->process($css);
    }
}
