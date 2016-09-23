<?php

namespace PostCSS\SourceMap;

use PostCSS\SourceMap\Consumer\Consumer;

/**
 * @link https://github.com/floridoo/concat-with-sourcemaps/blob/master/index.js
 */
class Concat
{
    /**
     * @var int
     */
    protected $lineOffset;
    /**
     * @var int
     */
    protected $columnOffset;

    protected $sourceMapping;
    /**
     * string[].
     */
    protected $contentParts;
    /**
     * string.
     */
    protected $separator;
    /**
     * @var Generator
     */
    protected $sourceMap = null;

    protected $separatorLineOffset = null;

    protected $separatorColumnOffset = null;

    /**
     * @param bool $generateSourceMap
     * @param string $fileName
     * @param string $separator
     */
    public function __construct($generateSourceMap = false, $fileName = '', $separator = '')
    {
        $this->lineOffset = 0;
        $this->columnOffset = 0;
        $this->sourceMapping = (bool) $generateSourceMap;
        $this->contentParts = [];
        $this->separator = (string) $separator;

        if ($this->sourceMapping) {
            $this->sourceMap = new Generator(['file' => str_replace('\\', '/', $fileName)]);
            $this->separatorLineOffset = 0;
            $this->separatorColumnOffset = 0;
            $separatorString = $this->separator;
            $separatorStringLength = strlen($separatorString);
            for ($i = 0; $i < $separatorStringLength; ++$i) {
                ++$this->separatorColumnOffset;
                if ($separatorString[$i] === "\n") {
                    ++$this->separatorLineOffset;
                    $this->separatorColumnOffset = 0;
                }
            }
        }
    }

    /**
     * @param string $filePath
     * @param string $content
     * @param string|SourceMap $sourceMap JSON encoded sourcemap, or SourceMap instance
     */
    public function add($filePath, $content, $sourceMap = null)
    {
        $filePath = str_replace('\\', '/', (string) $filePath);
        $content = (string) $content;
        if (!empty($this->contentParts)) {
            $this->contentParts[] = $this->separator;
        }
        $this->contentParts[] = $content;
        if ($this->sourceMapping) {
            $lines = count(explode("\n", $content));
            if (is_string($sourceMap)) {
                $sourceMap = ($sourceMap === '') ? new SourceMap(json_decode($sourceMap, true)) : null;
            }
            if ($sourceMap !== null && !empty($sourceMap->mappings)) {
                $upstreamSM = Consumer::construct($sourceMap);
                $_this = $this;
                $upstreamSM->eachMapping(function (Mapping $mapping) use ($_this) {
                    if ($mapping->source !== '') {
                        $_this->sourceMap->addMapping([
                            'generated' => [
                                'line' => $_this->lineOffset + $mapping->generatedLine,
                                'column' => ($mapping->generatedLine === 1 ? $_this->columnOffset : 0) + $mapping->generatedColumn,
                            ],
                            'original' => [
                                'line' => $mapping->originalLine,
                                'column' => $mapping->originalColumn,
                            ],
                            'source' => $mapping->source,
                            'name' => $mapping->name,
                        ]);
                    }
                });
                if ($upstreamSM->sourcesContent) {
                    $upstreamSM->sourcesContent->forEach(function ($sourceContent, $i) {
                        $_this->_sourceMap->setSourceContent($upstreamSM->sources[$i], $sourceContent);
                    });
                }
            } else {
                if ($sourceMap !== null && !empty($sourceMap->sources)) {
                    $filePath = $sourceMap->sources[0];
                }
                if ($filePath !== '') {
                    for ($i = 1; $i <= $lines; ++$i) {
                        $this->sourceMap->addMapping([
                            'generated' => [
                                'line' => $this->lineOffset + $i,
                                'column' => $i === 1 ? $this->columnOffset : 0,
                            ],
                            'original' => [
                                'line' => $i,
                                'column' => 0,
                            ],
                            'source' => $filePath,
                        ]);
                    }
                    if ($sourceMap !== null && (!empty($sourceMap->sourcesContent))) {
                        $this->sourceMap->setSourceContent($filePath, $sourceMap->sourcesContent[0]);
                    }
                }
            }
            if ($lines > 1) {
                $this->columnOffset = 0;
            }
            if ($this->separatorLineOffset === 0) {
                $this->columnOffset += strlen($content);
                $p = strrpos($content, "\n");
                if ($p !== false) {
                    $this->columnOffset -= $p + 1;
                }
            }
            $this->columnOffset += $this->separatorColumnOffset;
            $this->lineOffset += $lines - 1 + $this->separatorLineOffset;
        }
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return implode('', $this->contentParts);
    }

    /**
     * @return string
     */
    public function getSourceMapAsString()
    {
        return $this->sourceMap ? (string) $this->sourceMap : '';
    }
}
