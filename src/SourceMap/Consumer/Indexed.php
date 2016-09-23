<?php

namespace PostCSS\SourceMap\Consumer;

use PostCSS\SourceMap\SourceMap;

/**
 * @link https://github.com/mozilla/source-map/blob/master/lib/source-map-consumer.js
 */
class Indexed extends Consumer
{
    const VERSION = 3;

    /**
     * @var array
     */
    protected $sources;

    /**
     * @var array
     */
    protected $names;

    /**
     * @var array
     */
    protected $sections;

    /**
     * @param SourceMap|string $sourceMap
     */
    public function __construct($aSourceMap)
    {
        $sourceMap = is_string($aSourceMap) ? new SourceMap(json_decode(preg_replace('/^\)\]\}\'/', '', $aSourceMap))) : $aSourceMap;
        if ($sourceMap->version !== static::VERSION) {
            throw new \Exception('Unsupported version: '.$sourceMap->version);
        }
        $this->sources = [];
        $this->names = [];
        $lastOffset = [
            'line' => -1,
            'column' => 0,
        ];
        $this->sections = array_map(
            function ($s) use (&$lastOffset) {
                if ($s['url']) {
                    // The url field will require support for asynchronicity.
                    // See https://github.com/mozilla/source-map/issues/16
                    throw new \Exception('Support for url field in sections not implemented.');
                }
                $offset = (int) $s['offset'];
                $offsetLine = (int) $s['line'];
                $offsetColumn = (int) $s['column'];
                if ($offsetLine < $lastOffset['line'] || ($offsetLine === $lastOffset['line'] && $offsetColumn < $lastOffset['column'])) {
                    throw new \Exception('Section offsets must be ordered and non-overlapping.');
                }
                $lastOffset['line'] = $offset['line'];
                $lastOffset['column'] = $offset['column'];

                return [
                    'generatedOffset' => [
                        // The offset fields are 0-based, but we use 1-based indices when
                        // encoding/decoding from VLQ.
                        'generatedLine' => $offsetLine + 1,
                        'generatedColumn' => $offsetColumn + 1,
                    ],
                    'consumer' => Consumer::construct($s['map']),
                ];
            },
            $sourceMap->sections
        );
    }

    public function getSources()
    {
        $sources = [];
        $numSections = count($this->sections);
        for ($i = 0; $i < $numSections; ++$i) {
            $sources = array_merge($sources, $this->sections[$i]['consumer']->sources);
        }

        return $sources;
    }

    /**
     * {@inheritdoc}
     *
     * @see Consumer::originalPositionFor()
     */
    public function originalPositionFor(array $aArgs)
    {
        throw new NotImplementedException();
    }

    public function hasContentsOfAllSources()
    {
        throw new NotImplementedException();
    }

    public function sourceContentFor($aSource, $nullOnMissing)
    {
        throw new NotImplementedException();
    }

    public function generatedPositionFor($aArgs)
    {
        throw new NotImplementedException();
    }

    protected function parseMappings($aStr, $aSourceRoot)
    {
        throw new NotImplementedException();
    }
}
