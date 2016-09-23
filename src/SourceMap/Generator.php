<?php

namespace PostCSS\SourceMap;

use PostCSS\Path\Mozilla as Util;
use PostCSS\Encoding;

/**
 * @link https://github.com/mozilla/source-map/blob/master/lib/source-map-generator.js
 * @link https://github.com/mozilla/source-map/blob/master/lib/util.js
 */
class Generator
{
    const VERSION = 3;

    /**
     * @var string
     */
    public $file;

    /**
     * @var string
     */
    public $sourceRoot;

    /**
     * @var bool
     */
    protected $skipValidation;

    /**
     * @var array
     */
    public $sources;

    /**
     * @var array
     */
    public $names;

    /**
     * @var MappingList
     */
    public $mappings;

    /**
     * @var null|string[]
     */
    protected $sourcesContents;

    public function __construct(array $args = [])
    {
        $this->file = isset($args['file']) ? $args['file'] : '';
        $this->sourceRoot = isset($args['sourceRoot']) ? $args['sourceRoot'] : '';
        $this->skipValidation = isset($args['skipValidation']) ? (bool) $args['skipValidation'] : false;
        $this->sources = [];
        $this->names = [];
        $this->mappings = new MappingList();
        $this->sourcesContents = null;
    }

    /**
     * Creates a new SourceMapGenerator based on a SourceMapConsumer.
     *
     * @param Consumer\Consumer aSourceMapConsumer The SourceMap
     *
     * @return static
     */
    public static function fromSourceMap(Consumer\Consumer $aSourceMapConsumer)
    {
        $sourceRoot = $aSourceMapConsumer->sourceRoot;
        $generator = new static([
            'file' => $aSourceMapConsumer->file,
            'sourceRoot' => $sourceRoot,
        ]);
        $aSourceMapConsumer->eachMapping(function (Mapping $mapping) use ($sourceRoot, $generator) {
            $newMapping = [
                'generated' => [
                    'line' => $mapping->generatedLine,
                    'column' => $mapping->generatedColumn,
                ],
            ];
            if ($mapping->source) {
                $newMapping['source'] = $mapping->source;
                if ($sourceRoot) {
                    $newMapping['source'] = Util::relative($sourceRoot, $newMapping['source']);
                }
                $newMapping['original'] = [
                    'line' => $mapping->originalLine,
                    'column' => $mapping->originalColumn,
                ];
                if ($mapping->name) {
                    $newMapping['name'] = $mapping->name;
                }
            }
            $generator->addMapping($newMapping);
        });
        foreach ($aSourceMapConsumer->sources as $sourceFile) {
            $content = $aSourceMapConsumer->sourceContentFor($sourceFile);
            if ($content) {
                $generator->setSourceContent($sourceFile, $content);
            }
        }

        return $generator;
    }

    public function addMapping(array $aArgs)
    {
        $generated = isset($aArgs['generated']) ? $aArgs['generated'] : null;
        $original = isset($aArgs['original']) ? $aArgs['original'] : null;
        $source = isset($aArgs['source']) ? (string) $aArgs['source'] : '';
        $name = isset($aArgs['name']) ? (string) $aArgs['name'] : '';

        if (!$this->skipValidation) {
            static::validateMapping($generated, $original, $source, $name);
        }

        if ($source !== '') {
            if (!in_array($source, $this->sources, true)) {
                $this->sources[] = $source;
            }
        }

        if ($name !== '') {
            if (!in_array($name, $this->names, true)) {
                $this->names[] = $name;
            }
        }

        $this->mappings->add(new Mapping([
            'generatedLine' => (is_array($generated) && isset($generated['line'])) ? $generated['line'] : null,
            'generatedColumn' => (is_array($generated) && isset($generated['column'])) ? $generated['column'] : null,
            'originalLine' => (is_array($original) && isset($original['line'])) ? $original['line'] : null,
            'originalColumn' => (is_array($original) && isset($original['column'])) ? $original['column'] : null,
            'source' => $source,
            'name' => $name,
        ]));
    }

    /**
     * Set/unset the source content for a source file.
     *
     * @param string $aSourceFile
     * @param string|null $aSourceContent Set to null to remove, string to add
     */
    public function setSourceContent($aSourceFile, $aSourceContent = null)
    {
        $source = $aSourceFile;
        if ($this->sourceRoot !== '') {
            $source = Util::relative($this->sourceRoot, $source);
        }
        $key = (string) $source;

        if (isset($aSourceContent)) {
            // Add the source content to the sourcesContents map.
            // Create a new sourcesContents map if the property is null.
            if ($this->sourcesContents === null) {
                $this->sourcesContents = [];
            }
            $this->sourcesContents[$key] = (string) $aSourceContent;
        } elseif ($this->sourcesContents !== null) {
            // Remove the source file from the sourcesContents map.
            // If the sourcesContents map is empty, set the property to null.
            unset($this->sourcesContents[$key]);
            if (empty($this->sourcesContents)) {
                $this->sourcesContents = null;
            }
        }
    }

    public function applySourceMap($aSourceMapConsumer, $aSourceFile = null, $aSourceMapPath = null)
    {
        $sourceFile = $aSourceFile;
        // If $aSourceFile is omitted, we will use the file property of the SourceMap
        if (!$aSourceFile) {
            if (!$aSourceMapConsumer->file) {
                throw new \Exception('SourceMapGenerator.prototype.applySourceMap requires either an explicit source file, or the source map\'s "file" property. Both were omitted.');
            }
            $sourceFile = $aSourceMapConsumer->file;
        }
        $sourceRoot = $this->sourceRoot;
        // Make "$sourceFile" relative if an absolute Url is passed.
        if ($sourceRoot) {
            $sourceFile = Util::relative($sourceRoot, $sourceFile);
        }
        // Applying the SourceMap can add and remove items from the sources and
        // the names array.
        $newSources = [];
        $newNames = [];

        // Find mappings for the "$sourceFile"
        $this->mappings->unsortedForEach(
            function (Mapping $mapping, $me) use ($sourceFile, $aSourceMapConsumer, $aSourceMapPath, $sourceRoot, &$newSources, &$newNames) {
                if ($mapping->source === $sourceFile && $mapping->originalLine) {
                    // Check if it can be mapped by the source map, then update the mapping.
                    $original = $aSourceMapConsumer->originalPositionFor([
                        'line' => $mapping->originalLine,
                        'column' => $mapping->originalColumn,
                    ]);
                    if (isset($original['source']) && $original['source']) {
                        // Copy mapping
                        $mapping->source = $original['source'];
                        if ($aSourceMapPath) {
                            $mapping->source = Util::join($aSourceMapPath, $mapping->source);
                        }
                        if ($sourceRoot) {
                            $mapping->source = Util::relative($sourceRoot, $mapping->source);
                        }
                        $mapping->originalLine = $original['line'];
                        $mapping->originalColumn = $original['column'];
                        if (isset($original['name']) && $original['name']) {
                            $mapping->name = $original['name'];
                        }
                    }
                }

                $source = $mapping->source;
                if ($source && !in_array($source, $newSources, true)) {
                    $newSources[] = $source;
                }

                $name = $mapping->name;
                if ($name && !in_array($name, $newNames, true)) {
                    $newNames[] = $name;
                }
            },
            $this
        );
        $this->sources = $newSources;
        $this->names = $newNames;
        // Copy sourcesContents of applied map.
        foreach ($aSourceMapConsumer->sources as $sourceFile) {
            $content = $aSourceMapConsumer->sourceContentFor($sourceFile);
            if ($content) {
                if ($aSourceMapPath) {
                    $sourceFile = Util::join($aSourceMapPath, $sourceFile);
                }
                if ($sourceRoot) {
                    $sourceFile = Util::relative($sourceRoot, $sourceFile);
                }
                $this->setSourceContent($sourceFile, $content);
            }
        }
    }

    protected static function validateMapping($aGenerated, $aOriginal, $aSource, $aName)
    {
        if (
            is_array($aGenerated) && isset($aGenerated['line']) && isset($aGenerated['column'])
            && ((int) $aGenerated['line']) > 0 && ((int) $aGenerated['column']) >= 0
            && $aOriginal === null && ((string) $aSource) === '' && ((string) $aName) === ''
        ) {
            // Case 1.
            return;
        } elseif (
            is_array($aGenerated) && isset($aGenerated['line']) && isset($aGenerated['column'])
            && is_array($aOriginal) && isset($aOriginal['line']) && isset($aOriginal['column'])
            && ((int) $aGenerated['line']) > 0 && ((int) $aGenerated['column']) >= 0
            && ((int) $aOriginal['line']) > 0 && ((int) $aOriginal['column']) >= 0
            && ((string) $aSource) !== ''
        ) {
            // Cases 2 and 3.
            return;
        } else {
            throw new \Exception('Invalid mapping: ' + json_encode(func_get_args()));
        }
    }

    /**
     * Serialize the accumulated mappings in to the stream of base 64 VLQs
     * specified by the source map format.
     */
    protected function serializeMappings()
    {
        $previousGeneratedColumn = 0;
        $previousGeneratedLine = 1;
        $previousOriginalColumn = 0;
        $previousOriginalLine = 0;
        $previousName = 0;
        $previousSource = 0;
        $result = '';

        $mappings = $this->mappings->toArray();
        for ($i = 0, $len = count($mappings); $i < $len; ++$i) {
            $mapping = $mappings[$i];
            $next = '';
            if ($mapping->generatedLine !== $previousGeneratedLine) {
                $previousGeneratedColumn = 0;
                while ($mapping->generatedLine !== $previousGeneratedLine) {
                    $next .= ';';
                    ++$previousGeneratedLine;
                }
            } else {
                if ($i > 0) {
                    if (!Mapping::compareByGeneratedPositionsInflated($mapping, $mappings[$i - 1])) {
                        continue;
                    }
                    $next .= ',';
                }
            }

            $next .= Encoding::toBase64VLQ($mapping->generatedColumn - $previousGeneratedColumn);
            $previousGeneratedColumn = $mapping->generatedColumn;

            if ($mapping->source !== '') {
                $sourceIdx = array_search($mapping->source, $this->sources, true);
                if ($sourceIdx === false) {
                    $sourceIdx = -1;
                }
                $next .= Encoding::toBase64VLQ($sourceIdx - $previousSource);
                $previousSource = $sourceIdx;
                // lines are stored 0-based in SourceMap spec version 3
                $next .= Encoding::toBase64VLQ($mapping->originalLine - 1 - $previousOriginalLine);
                $previousOriginalLine = $mapping->originalLine - 1;

                $next .= Encoding::toBase64VLQ($mapping->originalColumn - $previousOriginalColumn);
                $previousOriginalColumn = $mapping->originalColumn;

                if ($mapping->name !== '') {
                    $nameIdx = array_search($mapping->name, $this->names);
                    if ($nameIdx === false) {
                        $nameIdx = -1;
                    }
                    $next .= Encoding::toBase64VLQ($nameIdx - $previousName);
                    $previousName = $nameIdx;
                }
            }
            $result .= $next;
        }

        return $result;
    }

    /**
     * @param string[] $aSources
     * @param string $aSourceRoot
     *
     * @return array
     */
    public function generateSourcesContent(array $aSources, $aSourceRoot)
    {
        if ($this->sourcesContents === null) {
            return array_fill(0, count($aSources), null);
        }
        $me = $this;

        return array_map(
            function ($source) use ($me, $aSourceRoot) {
                if ($aSourceRoot !== '') {
                    $source = Util::relative($aSourceRoot, $source);
                }
                $key = (string) $source;

                return isset($me->sourcesContents[$key]) ? $me->sourcesContents[$key] : null;
            },
            $aSources
        );
    }

    /**
     * @return array
     */
    public function toJSON()
    {
        $map = [
            'version' => static::VERSION,
            'sources' => $this->sources,
            'names' => $this->names,
            'mappings' => $this->serializeMappings(),
        ];
        if ($this->file !== '') {
            $map['file'] = $this->file;
        }
        if ($this->sourceRoot !== '') {
            $map['sourceRoot'] = $this->sourceRoot;
        }
        if ($this->sourcesContents !== null) {
            $map['sourcesContent'] = $this->generateSourcesContent($this->sources, $this->sourceRoot);
        }

        return $map;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toJSON());
    }
}
