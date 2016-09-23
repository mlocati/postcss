<?php

namespace PostCSS\Plugin;

use PostCSS\Root;
use PostCSS\Result;

interface PluginInterface
{
    /**
     * Required PostCSS version.
     *
     * @var string
     */
    const POSTCSS_VERSION = '';

    /**
     * Return the plugin name.
     *
     * @return string
     */
    public function getName();

    public function setOptions(array $opts);

    public function run(Root $root, Result $result);
}
