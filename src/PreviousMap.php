<?php

namespace PostCSS;

use PostCSS\SourceMap\Consumer\Consumer as MozillaSourceMapConsumer;
use PostCSS\SourceMap\Generator as MozillaSourceMapGenerator;
use PostCSS\Path\NodeJS as Path;

/**
 * Source map information from input CSS.
 * For example, source map after Sass compiler.
 *
 * This class will automatically find source map in input CSS or in file system near input file (according `from` option).
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/previous-map.es6
 */
class PreviousMap
{
    /**
     * @var string
     */
    public $annotation = '';

    /**
     * @var bool
     */
    public $inline;

    /**
     * @var string|null
     */
    public $text = null;

    /**
     * @var MozillaSourceMapConsumer|null
     */
    private $consumerCache = null;

    /**
     * @param string $css Input CSS source
     * @param processOptions $opts {@link Processor#process} options
     */
    public function __construct($css, array $opts)
    {
        $this->loadAnnotation($css);
        $this->inline = $this->startWith($this->annotation, 'data:');
        $prev = (isset($opts['map']) && $opts['map'] && isset($opts['map']['prev'])) ? $opts['map']['prev'] : null;
        $text = $this->loadMap(isset($opts['from']) ? $opts['from'] : null, $prev);
        if ($text) {
            $this->text = $text;
        }
    }

    /**
     * Create a instance of `SourceMapGenerator` class from the `source-map` library to work with source map information.
     *
     * It is lazy method, so it will create object only on first call and then it will use cache.
     *
     * @return MozillaSourceMapConsumer object woth source map information
     */
    public function consumer()
    {
        if ($this->consumerCache === null) {
            $this->consumerCache = MozillaSourceMapConsumer::construct($this->text);
        }

        return $this->consumerCache;
    }

    /**
     * Does source map contains `sourcesContent` with input source text.
     *
     * @return {boolean} Is `sourcesContent` present
     */
    public function withContent()
    {
        $sc = $this->consumer()->sourcesContent;

        return !empty($sc);
    }

    public function startWith($string, $start)
    {
        return is_string($string) && $string !== '' && substr($string, 0, strlen($start)) === $start;
    }

    public function loadAnnotation($css)
    {
        if (preg_match('/\/\*\s*# sourceMappingURL=(.*)\s*\*\//', $css, $match)) {
            $this->annotation = trim($match[1]);
        }
    }

    public function decodeInline($text)
    {
        $utfd64 = 'data:application/json;charset=utf-8;base64,';
        $utf64 = 'data:application/json;charset=utf8;base64,';
        $b64 = 'data:application/json;base64,';
        $uri = 'data:application/json,';

        if ($this->startWith($text, $uri)) {
            return urldecode(substr($text, strlen($uri)));
        } elseif ($this->startWith($text, $b64)) {
            return base64_decode(substr($text, strlen($b64)));
        } elseif ($this->startWith($text, $utf64)) {
            return base64_decode(substr($text, strlen($utf64)));
        } elseif ($this->startWith($text, $utfd64)) {
            return base64_decode(substr($text, strlen($utfd64)));
        } else {
            if (preg_match('/data:application\/json;([^,<n]+),/', $text, $match)) {
                $encoding = $match[1];
            } else {
                $encoding = null;
            }
            throw new Exception\UnsupportedSourceMapEncoding($encoding);
        }
    }

    public function loadMap($file, $prev)
    {
        if ($prev === false) {
            return false;
        }

        if ($prev) {
            if (is_string($prev)) {
                return $prev;
            } elseif (is_callable($prev)) {
                $prevPath = call_user_func($prev, $file);
                if ($prevPath && is_file($prevPath)) {
                    return trim(file_get_contents($prevPath));
                } else {
                    throw new Exception\UnableToLoadPreviousSourceMap($prevPath);
                }
            } elseif ($prev instanceof MozillaSourceMapConsumer) {
                return (string) MozillaSourceMapGenerator::fromSourceMap($prev);
            } elseif ($prev instanceof MozillaSourceMapGenerator) {
                return (string) $prev;
            } elseif ($this->isMap($prev)) {
                return json_encode($prev);
            } else {
                throw new Exception\UnsupportedPreviousSourceMapFormat(json_encode($prev));
            }
        } elseif ($this->inline) {
            return $this->decodeInline($this->annotation);
        } elseif ($this->annotation !== '') {
            $map = $this->annotation;
            if ($file) {
                $map = Path::join(dirname($file), $map);
            }
            $this->root = dirname($map);
            if (file_exists($map)) {
                return trim(file_get_contents($map));
            } else {
                return false;
            }
        }
    }

    public function isMap($map)
    {
        $result = false;
        if (is_array($map)) {
            if (isset($map['mappings']) && is_string($map['mappings'])) {
                $result = true;
            } elseif (isset($map['_mappings']) && is_string($map['_mappings'])) {
                $result = true;
            }
        }

        return $result;
    }
}
