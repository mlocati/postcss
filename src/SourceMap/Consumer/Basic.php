<?php

namespace PostCSS\SourceMap\Consumer;

use PostCSS\SourceMap\SourceMap;
use PostCSS\Path\Mozilla as Util;
use PostCSS\SourceMap\Mapping;
use PostCSS\SourceMap\BinarySearch;
use PostCSS\Encoding;
use PostCSS\SourceMap\Generator;

/**
 * @link https://github.com/mozilla/source-map/blob/master/lib/source-map-consumer.js
 */
class Basic extends Consumer
{
    /**
     * @var string
     */
    public $file;

    const VERSION = 3;

    /**
     * @param SourceMap|string|null $sourceMap
     */
    public function __construct($aSourceMap)
    {
        if ($aSourceMap === null) {
            return;
        }
        $sourceMap = is_string($aSourceMap) ? new SourceMap(json_decode(preg_replace('/^\)\]\}\'/', '', $aSourceMap))) : $aSourceMap;
        // Sass 3.3 leaves out the 'names' array, so we deviate from the spec (which
        // requires the array) to play nice here.
        $names = $sourceMap->names;
        $sourceRoot = $sourceMap->sourceRoot;

        // Once again, Sass deviates from the spec and supplies the version as a
        // string rather than a number, so we use loose equality checking here.
        if ($sourceMap->version !== static::VERSION) {
            throw new \Exception('Unsupported version: '.$sourceMap->version);
        }
        $this->names = $sourceMap->names;
        $this->sources = array_map(
            function ($source) use ($sourceRoot) {
                $source = (string) $source;
                // Some source maps produce relative source paths like "./foo.js" instead of
                // "foo.js".  Normalize these first so that future comparisons will succeed.
                // See bugzil.la/1090768.
                $source = Util::normalize($source);
                // Always ensure that absolute sources are internally stored relative to
                // the source root, if the source root is absolute. Not doing this would
                // be particularly problematic when the source root is a prefix of the
                // source (valid, but why??). See github issue #199 and bugzil.la/1188982.
                return ($sourceRoot !== '' && Util::isAbsolute($sourceRoot) && Util::isAbsolute($source)) ? Util::relative($sourceRoot, $source) : $source;
            },
            $sourceMap->sources
        );
        $this->sourceRoot = $sourceRoot;
        $this->sourcesContent = $sourceMap->sourcesContent;
        $this->mappings = $sourceMap->mappings;
        $this->file = $sourceMap->file;
    }

    /**
     * Create a BasicSourceMapConsumer from a SourceMapGenerator.
     *
     * @param SourceMapGenerator $aSourceMap The source map that will be consumed
     *
     * @returns BasicSourceMapConsumer
     */
    public static function fromSourceMap(Generator $aSourceMap)
    {
        $smc = new static(null);

        $smc->names = $aSourceMap->names;
        $smc->sources = $aSourceMap->sources;
        $smc->sourceRoot = $aSourceMap->sourceRoot;
        $smc->sourcesContent = $aSourceMap->generateSourcesContent($smc->sources, $smc->sourceRoot);
        $smc->file = $aSourceMap->file;

        // Because we are modifying the entries (by converting string sources and
        // names to indices into the sources and names ArraySets), we have to make
        // a copy of the entry or else bad things happen. Shared mutable state
        // strikes again! See github issue #191.

        $generatedMappings = $aSourceMap->mappings->toArray();
        $smc->generatedMappings = [];
        $smc->originalMappings = [];

        for ($i = 0, $length = count($generatedMappings); $i < $length; ++$i) {
            $srcMapping = $generatedMappings[$i];
            $destMapping = new Mapping([
                'generatedLine' => $srcMapping->generatedLine,
                'generatedColumn' => $srcMapping->generatedColumn,
            ]);
            if ($srcMapping->source) {
                $destMapping->source = array_search($srcMapping->source, $smc->sources, true);
                if ($destMapping->source === false) {
                    $destMapping->source = -1;
                }
                $destMapping->originalLine = $srcMapping->originalLine;
                $destMapping->originalColumn = $srcMapping->originalColumn;

                if ($srcMapping->name) {
                    $destMapping->name = array_search($srcMapping->name, $smc->names, true);
                    if ($destMapping->name === false) {
                        $destMapping->name = -1;
                    }
                }

                $smc->originalMappings[] = $destMapping;
            }

            $smc->generatedMappings[] = $destMapping;
        }

        usort($smc->originalMappings, [Mapping::class, 'compareByOriginalPositions']);

        return $smc;
    }

    /**
     * Find the mapping that best matches the hypothetical "needle" mapping that
     * we are searching for in the given "haystack" of mappings.
     */
    public function findMapping(Mapping $aNeedle, array $aMappings, $aLineName, $aColumnName, $aComparator, $aBias)
    {
        // To return the position we are searching for, we must first find the
        // mapping for the given position and then return the opposite position it
        // points to. Because the mappings are sorted, we can use binary search to
        // find the best mapping.

        if (isset($aNeedle->aLineName) && $aNeedle->aLineName <= 0) {
            throw new \Exception('Line must be greater than or equal to 1, got '.$aNeedle['aLineName']);
        }
        if (isset($aNeedle->aColumnName) && $aNeedle->aColumnName < 0) {
            throw new \Exception('Column must be greater than or equal to 0, got '.$aNeedle['aColumnName']);
        }

        return BinarySearch::search($aNeedle, $aMappings, $aComparator, $aBias);
    }

    public function computeColumnSpans()
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     *
     * @see Consumer::originalPositionFor()
     */
    public function originalPositionFor(array $aArgs)
    {
        $needle = new Mapping([
            'generatedLine' => $aArgs['line'],
            'generatedColumn' => $aArgs['column'],
        ]);
        $generatedMappings = $this->getGeneratedMappings();
        if ($generatedMappings) {
            $index = $this->findMapping(
                $needle,
                $generatedMappings,
                'generatedLine',
                'generatedColumn',
                [Mapping::class, 'compareByGeneratedPositionsDeflated'],
                isset($aArgs['bias']) ? $aArgs['bias'] : static::GREATEST_LOWER_BOUND
            );
        } else {
            $index = -1;
        }

        if ($index >= 0) {
            $mapping = $generatedMappings[$index];

            if ($mapping->generatedLine === $needle->generatedLine) {
                $source = '';
                if ($mapping->source !== null && isset($this->sources[$mapping->source])) {
                    $source = $this->sources[$mapping->source];
                    if ($this->sourceRoot != '') {
                        $source = Util::join($this->sourceRoot, $source);
                    }
                }
                $name = '';
                if (isset($mapping->name) && isset($this->names[$mapping->name])) {
                    $name = $this->names[$mapping->name];
                }

                return [
                    'source' => $source,
                    'line' => $mapping->originalLine,
                    'column' => $mapping->originalColumn,
                    'name' => $name,
                ];
            }
        }

        return null;
    }

    public function hasContentsOfAllSources()
    {
        throw new NotImplementedException();
    }

    public function sourceContentFor($aSource, $nullOnMissing = false)
    {
        if (empty($this->sourcesContent)) {
            return null;
        }

        if ($this->sourceRoot !== '') {
            $aSource = Util::relative($this->sourceRoot, $aSource);
        }

        if (in_array($aSource, $this->sources, true)) {
            return $this->sourcesContent[array_search($aSource, $this->sources, true)];
        }

        if ($this->sourceRoot !== '' && ($url = Util::urlParse($this->sourceRoot))) {
            // XXX: file:// URIs and absolute paths lead to unexpected behavior for
            // many users. We can help them out when they expect file:// URIs to
            // behave like it would if they were running a local HTTP server. See
            // https://bugzilla.mozilla.org/show_bug.cgi?id=885597.
            $fileUriAbsPath = preg_replace('/^file:\/\//', '', $aSource);
            if ($url['scheme'] == 'file' && in_array($fileUriAbsPath, $this->sources, true)) {
                return $this->sourcesContent[array_search($fileUriAbsPath, $this->sources, true)];
            }

            if (($url['path'] === '' || $url['path'] == '/') && in_array('/'.$aSource, $this->sources, true)) {
                return $this->sourcesContent[array_search('/'.$aSource, $this->sources, true)];
            }
        }

        // This function is used recursively from
        // IndexedSourceMapConsumer.prototype.sourceContentFor. In that case, we
        // don't want to throw if we can't find the source - we just want to
        // return null, so we provide a flag to exit gracefully.
        if ($nullOnMissing) {
            return null;
        } else {
            throw new \Exception('"'.$aSource.'" is not in the SourceMap.');
        }
    }

    public function generatedPositionFor($aArgs)
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     *
     * @see Consumer::parseMappings()
     */
    protected function parseMappings($aStr, $aSourceRoot)
    {
        $generatedLine = 1;
        $previousGeneratedColumn = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $previousSource = 0;
        $previousName = 0;
        $length = strlen($aStr);
        $index = 0;
        $cachedSegments = [];
        $temp = [];
        $originalMappings = [];
        $generatedMappings = [];

        while ($index < $length) {
            if ($aStr[$index] === ';') {
                ++$generatedLine;
                ++$index;
                $previousGeneratedColumn = 0;
            } elseif ($aStr[$index] === ',') {
                ++$index;
            } else {
                $mapping = new Mapping([
                    'generatedLine' => $generatedLine,
                ]);
                // Because each offset is encoded relative to the previous one,
                // many segments often have the same encoding. We can exploit this
                // fact by caching the parsed variable length fields of each segment,
                // allowing us to avoid a second parse if we encounter the same
                // segment again.
                for ($end = $index; $end < $length; ++$end) {
                    if (static::charIsMappingSeparator($aStr, $end)) {
                        break;
                    }
                }
                $str = substr($aStr, $index, $end - $index);

                if (isset($cachedSegments[$str])) {
                    $segment = $cachedSegments[$str];
                    $index += strlen($str);
                } else {
                    $segment = [];
                    while ($index < $end) {
                        $temp = Encoding::fromBase64VLQ($aStr, $index, $temp);
                        $value = $temp['value'];
                        $index = $temp['rest'];
                        $segment[] = $value;
                    }
                    if (count($segment) === 2) {
                        throw new \Exception('Found a source, but no line and column');
                    }

                    if (count($segment) === 3) {
                        throw new \Exception('Found a source and line, but no column');
                    }

                    $cachedSegments[$str] = $segment;
                }

                // Generated column.
                $mapping->generatedColumn = $previousGeneratedColumn + $segment[0];
                $previousGeneratedColumn = $mapping->generatedColumn;

                if (count($segment) > 1) {
                    // Original source.
                    $mapping->source = $previousSource + $segment[1];
                    $previousSource += $segment[1];

                    // Original line.
                    $mapping->originalLine = $previousOriginalLine + $segment[2];
                    $previousOriginalLine = $mapping->originalLine;
                    // Lines are stored 0-based
                    $mapping->originalLine += 1;

                    // Original column.
                    $mapping->originalColumn = $previousOriginalColumn + $segment[3];
                    $previousOriginalColumn = $mapping->originalColumn;

                    if (count($segment) > 4) {
                        // Original name.
                        $mapping->name = $previousName + $segment[4];
                        $previousName += $segment[4];
                    }
                }

                $generatedMappings[] = $mapping;
                if ($mapping->originalLine !== null) {
                    $originalMappings[] = $mapping;
                }
            }
        }

        usort($generatedMappings, [Mapping::class, 'compareByGeneratedPositionsDeflated']);
        usort($generatedMappings, [Mapping::class, 'compareByGeneratedPositionsDeflated']);
        $this->generatedMappings = $generatedMappings;
        usort($originalMappings, [Mapping::class, 'compareByOriginalPositions']);
        $this->originalMappings = $originalMappings;
    }
}
