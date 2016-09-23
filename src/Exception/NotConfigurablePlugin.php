<?php

namespace PostCSS\Exception;

use PostCSS\Plugin\PluginInterface;

class NotConfigurablePlugin extends Exception
{
    /**
     * @var PluginInterface
     */
    protected $plugin;

    /**
     * @param PluginInterface $plugin
     * @param int|null $code
     * @param \Exception|null $previous
     */
    public function __construct(PluginInterface $plugin, $code = null, $previous = null)
    {
        $this->plugin = $plugin;
        parent::__construct('This plugin does not have a configurable operation', $code, $previous);
    }

    /**
     * @return PluginInterface
     */
    public function getPlugin()
    {
        return $this->sourceMapLocation;
    }
}
