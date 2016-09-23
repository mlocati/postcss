<?php

namespace PostCSS\SourceMap;

/**
 * @link https://github.com/mozilla/source-map/blob/master/lib/util.js
 */
class Mapping
{
    /**
     * @var int|null
     */
    public $generatedLine;

    /**
     * @var int|null
     */
    public $generatedColumn;

    /**
     * @var string
     */
    public $source;

    /**
     * @var int|null
     */
    public $originalLine;

    /**
     * @var int|null
     */
    public $originalColumn;

    /**
     * @var string
     */
    public $name;

    public $aLineName;

    public $aColumnName;

    public function __construct(array $opts = [])
    {
        $this->generatedLine = isset($opts['generatedLine']) ? (int) $opts['generatedLine'] : null;
        $this->generatedColumn = isset($opts['generatedColumn']) ? (int) $opts['generatedColumn'] : null;
        $this->source = isset($opts['source']) ? (string) $opts['source'] : '';
        $this->originalLine = isset($opts['originalLine']) ? (int) $opts['originalLine'] : null;
        $this->originalColumn = isset($opts['originalColumn']) ? (int) $opts['originalColumn'] : null;
        $this->name = isset($opts['name']) ? (string) $opts['name'] : null;
        $this->aLineName = isset($opts['aLineName']) ? $opts['aLineName'] : null;
    }

    /**
     * Comparator between two mappings with inflated source and name strings where
     * the generated positions are compared.
     *
     * @param Mapping $mappingA
     * @param Mapping $mappingB
     *
     * @return int
     */
    public static function compareByGeneratedPositionsInflated(Mapping $mappingA, Mapping $mappingB)
    {
        $cmp = ($mappingA->generatedLine ?: 0) - ($mappingB->generatedLine ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->generatedColumn ?: 0) - ($mappingB->generatedColumn ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = strcmp($mappingA->source, $mappingB->source);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->originalLine ?: 0) - ($mappingB->originalLine ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->originalColumn ?: 0) - ($mappingB->originalColumn ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp($mappingA->name, $mappingB->name);
    }

    public static function compareByGeneratedPositionsDeflated(Mapping $mappingA, Mapping $mappingB, $onlyCompareGenerated = false)
    {
        $cmp = ($mappingA->generatedLine ?: 0) - ($mappingB->generatedLine ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->generatedColumn ?: 0) - ($mappingB->generatedColumn ?: 0);
        if ($cmp !== 0 || $onlyCompareGenerated) {
            return $cmp;
        }

        $cmp = strcmp($mappingA->source, $mappingB->source);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->originalLine ?: 0) - ($mappingB->originalLine ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->originalColumn ?: 0) - ($mappingB->originalColumn ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp($mappingA->name, $mappingB->name);
    }

    /**
     * Comparator between two mappings where the original positions are compared.
     *
     * Optionally pass in `true` as `onlyCompareGenerated` to consider two
     * mappings with the same original source/line/column, but different generated
     * line and column the same. Useful when searching for a mapping with a
     * stubbed out mapping.
     */
    public static function compareByOriginalPositions(Mapping $mappingA, Mapping $mappingB, $onlyCompareOriginal = false)
    {
        $cmp = strcmp($mappingA->source, $mappingB->source);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->originalLine ?: 0) - ($mappingB->originalLine ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->originalColumn ?: 0) - ($mappingB->originalColumn ?: 0);
        if ($cmp !== 0 || $onlyCompareOriginal) {
            return $cmp;
        }

        $cmp = ($mappingA->generatedColumn ?: 0) - ($mappingB->generatedColumn ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = ($mappingA->generatedLine ?: 0) - ($mappingB->generatedLine ?: 0);
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp($mappingA->name, $mappingB->name);
    }
}
