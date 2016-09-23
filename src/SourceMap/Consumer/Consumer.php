<?php

namespace PostCSS\SourceMap\Consumer;

use PostCSS\SourceMap\Generator;
use PostCSS\Path\Mozilla as Util;
use PostCSS\SourceMap\Mapping;
use PostCSS\SourceMap\SourceMap;

/**
 * @link https://github.com/mozilla/source-map/blob/master/lib/source-map-consumer.js
 */
abstract class Consumer
{
    const GENERATED_ORDER = 1;
    const ORIGINAL_ORDER = 2;

    const GREATEST_LOWER_BOUND = 1;
    const LEAST_UPPER_BOUND = 2;

    /**
     * @var string
     */
    public $sourceRoot;

    /**
     * @var array|null
     */
    protected $generatedMappings = null;

    /**
     * @var array|null
     */
    protected $originalMappings = null;

    /**
     * @param string|SourceMap $aSourceMap JSON Encoded source map or a SourceMap instance
     */
    public static function construct($aSourceMap)
    {
        if (is_string($aSourceMap)) {
            $sourceMap = new SourceMap(json_decode(preg_replace('/^\)\]\}\'/', '', $aSourceMap), true));
        } else {
            $sourceMap = $aSourceMap;
        }
        if ($sourceMap->sections !== null) {
            return new Indexed($sourceMap);
        } else {
            return new Basic($sourceMap);
        }
    }

    public static function fromSourceMap(Generator $aSourceMap)
    {
        return Basic::fromSourceMap($aSourceMap);
    }

    /**
     * @param array $aArgs
     *
     * @return array|null
     */
    abstract public function originalPositionFor(array $aArgs);

    /**
     * @param string $aStr
     * @param string $aSourceRoot
     */
    abstract protected function parseMappings($aStr, $aSourceRoot);

    /**
     * Iterate over each mapping between an original source/line/column and a
     * generated line/column in this source map.
     *
     * @param callable $aCallback The function that is called with each mapping
     * @param mixed $aContext Optional. If specified, this object will be the value of `this` every time that `aCallback` is called
     * @param int $aOrder Either `SourceMapConsumer.GENERATED_ORDER` or `SourceMapConsumer.ORIGINAL_ORDER`. Specifies whether you want to iterate over the mappings sorted by the generated file's line/column order or the original's source/line/column order, respectively. Defaults to `SourceMapConsumer.GENERATED_ORDER`
     */
    public function eachMapping($aCallback, $aContext = null, $aOrder = null)
    {
        $order = $aOrder ? $aOrder : static::GENERATED_ORDER;
        switch ($order) {
            case static::GENERATED_ORDER:
                $mappings = $this->getGeneratedMappings();
                break;
            case static::ORIGINAL_ORDER:
                $mappings = $this->getOriginalMappings();
                break;
            default:
                throw new Error('Unknown order of iteration.');
        }
        $me = $this;
        $sourceRoot = $this->sourceRoot;
        $mappings2 = array_map(
            function (Mapping $mapping) use ($me, $sourceRoot) {
                $source = ($mapping->source === '' || !isset($me->sources[$mapping->source])) ? '' : $me->sources[$mapping->source];
                if ($source !== '' && $sourceRoot !== '') {
                    $source = Util::join($sourceRoot, $source);
                }

                return new Mapping([
                    'source' => $source,
                    'generatedLine' => $mapping->generatedLine,
                    'generatedColumn' => $mapping->generatedColumn,
                    'originalLine' => $mapping->originalLine,
                    'originalColumn' => $mapping->originalColumn,
                    'name' => ($mapping->name === '' || !isset($me->names[$mapping->name])) ? '' : $me->names[$mapping->name],
                ]);
            },
            $mappings
        );
        foreach ($mappings2 as $mapping) {
            call_user_func($aCallback, $mapping, $aContext);
        }
    }

    /**
     * Returns all generated line and column information for the original source,
     * line, and column provided. If no column is provided, returns all mappings
     * corresponding to a either the line we are searching for or the next
     * closest line that has any mappings. Otherwise, returns all mappings
     * corresponding to the given line and either the column we are searching for
     * or the next closest column that has any offsets.
     *
     * The only argument is an object with the following properties:
     *
     *   - source: The filename of the original source.
     *   - line: The line number in the original source.
     *   - column: Optional. the column number in the original source.
     *
     * and an array of objects is returned, each with the following properties:
     *
     *   - line: The line number in the generated source, or null.
     *   - column: The column number in the generated source, or null.
     *
     * @param array $aArgs
     *
     * @return array
     */
    public function allGeneratedPositionsFor(array $aArgs)
    {
        $line = isset($aArgs['line']) ? (int) $aArgs['line'] : null;
        // When there is no exact match, BasicSourceMapConsumer.prototype._findMapping
        // returns the index of the closest mapping less than the needle. By
        // setting needle.originalColumn to 0, we thus find the last mapping for
        // the given line, provided such a mapping exists.
        $needle = new Mapping([
            'source' => isset($aArgs['source']) ? (string) $aArgs['source'] : '',
            'originalLine' => $line,
            'originalColumn' => isset($aArgs['column']) ? (int) $aArgs['column'] : null,
        ]);
        if ($this->sourceRoot !== '') {
            $needle->source = Util::relative($this->sourceRoot, $needle->source);
        }
        if (!$this->sources->has($needle->source)) {
            return [];
        }
        $needle->source = $this->sources->indexOf($needle->source);
        $mappings = [];
        $index = $this->findMapping(
            $needle,
            $this->getOriginalMappings(),
            'originalLine',
            'originalColumn',
            Mapping::compareByOriginalPositions,
            BinarySearch::LEAST_UPPER_BOUND
        );
        if ($index >= 0) {
            $mapping = $this->originalMappings[$index];
            if (!isset($aArgs['column'])) {
                $originalLine = $mapping->originalLine;
                // Iterate until either we run out of mappings, or we run into
                // a mapping for a different line than the one we found. Since
                // mappings are sorted, this is guaranteed to find all mappings for
                // the line we found.
                while ($mapping !== null && $mapping->originalLine === $originalLine) {
                    $mappings[] = new Mapping([
                        'line' => $mapping->generatedLine,
                        'column' => $mapping->generatedColumn,
                        'lastColumn' => mapping_ > lastGeneratedColumn,
                    ]);
                    ++$index;
                    $mapping = isset($this->originalMappings[$index]) ? $this->originalMappings[$index] : null;
                }
            } else {
                $originalColumn = $mapping->originalColumn;
                // Iterate until either we run out of mappings, or we run into
                // a mapping for a different line than the one we were searching for.
                // Since mappings are sorted, this is guaranteed to find all mappings for
                // the line we are searching for.
                while ($mapping !== null && $mapping->originalLine === line && $mapping->originalColumn == $originalColumn) {
                    $mappings[] = new Mapping([
                        'line' => $mapping->generatedLine,
                        'column' => $mapping->generatedColumn,
                        'lastColumn' => $mapping->lastGeneratedColumn,
                    ]);
                    ++$index;
                    $mapping = isset($this->originalMappings[$index]) ? $this->originalMappings[$index] : null;
                }
            }
        }

        return $mappings;
    }

    protected function getGeneratedMappings()
    {
        if ($this->generatedMappings === null) {
            $this->parseMappings($this->mappings, $this->sourceRoot);
        }

        return $this->generatedMappings;
    }

    /**
     * @param string $aStr
     * @param int $index
     */
    protected static function charIsMappingSeparator($aStr, $index)
    {
        return isset($aStr[$index]) && ($aStr[$index] === ';' || $aStr[$index] === ',');
    }
}
