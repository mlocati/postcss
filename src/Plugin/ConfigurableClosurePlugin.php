<?php

namespace PostCSS\Plugin;

use PostCSS\Processor;
use PostCSS\Result;
use PostCSS\Root;
use PostCSS\Exception\NotConfigurablePlugin;

class ConfigurableClosurePlugin extends ClosurePlugin
{
    /**
     * @return ClosurePlugin
     */
    protected function getClosurePlugin(array $args)
    {
        $result = call_user_func_array($this->callable, $args);
        if (!is_callable($result)) {
            throw new NotConfigurablePlugin($this);
        }

        return new ClosurePlugin($result, $this->name);
    }

    public function run(Root $root, Result $result)
    {
        return $this->getClosurePlugin([])->run($root, $result);
    }

    public function __invoke()
    {
        return $this->getClosurePlugin(func_get_args());
    }

    public function process($css, array $options = [])
    {
        $plugin = $this->getClosurePlugin($options);

        return (new Processor([$plugin]))->process($css);
    }
}
