<?php

namespace PostCSS\SourceMap;

class SourceMap
{
    /**
     * @var int|null
     */
    public $version;

    /**
     * @var array
     */
    public $sources;

    /**
     * @var array
     */
    public $sourcesContent;

    /**
     * @var string
     */
    public $mappings;

    /**
     * @var string[]
     */
    public $names;

    /**
     * @var string
     */
    public $sourceRoot;

    /**
     * @var string
     */
    public $file;

    /**
     * @var null|array
     */
    public $sections;

    public function __construct(array $defaults = [])
    {
        $this->version = isset($defaults['version']) ? (int) $defaults['version'] : null;
        $this->sources = isset($defaults['sources']) ? (array) $defaults['sources'] : [];
        $this->sourcesContent = isset($defaults['sourcesContent']) ? (array) $defaults['sourcesContent'] : [];
        $this->names = isset($defaults['names']) ? (array) $defaults['names'] : [];
        $this->mappings = isset($defaults['mappings']) ? (string) $defaults['mappings'] : '';
        $this->sourceRoot = isset($defaults['sourceRoot']) ? (string) $defaults['sourceRoot'] : '';
        $this->file = isset($defaults['file']) ? (string) $defaults['file'] : '';
        $this->sections = isset($defaults['sections']) ? $defaults['sections'] : null;
    }
}
