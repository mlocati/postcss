<?php

namespace PostCSS;

use PostCSS\Path\NodeJS as Path;
use PostCSS\SourceMap\Consumer\Consumer as SourceMapConsumer;
use PostCSS\SourceMap\Generator as SourceMapGenerator;

/**
 * Source map information from input CSS.
 * For example, source map after Sass compiler.
 *
 * This class will automatically find source map in input CSS or in file system near input file (according `from` option).
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/previous-map.es6
 *
 * @example
 * $root = \PostCSS\Parser::parse($css, ['from' => 'a.sass.css']);
 * $root->input->map //=> PreviousMap
 */
class PreviousMap
{
    /**
     * @var string
     */
    public $annotation = '';

    /**
     * Was source map inlined by data-uri to input CSS.
     *
     * @var bool
     */
    public $inline;

    /**
     * @var string|null
     */
    public $text = null;

    /**
     * @var SourceMapConsumer|null
     */
    private $consumerCache = null;

    /**
     * @param string $css Input CSS source
     * @param array $opts Options
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
     * @return SourceMapConsumer object woth source map information
     */
    public function consumer()
    {
        if ($this->consumerCache === null) {
            $this->consumerCache = SourceMapConsumer::construct($this->text);
        }

        return $this->consumerCache;
    }

    /**
     * Does source map contains `sourcesContent` with input source text.
     *
     * @return bool Is `sourcesContent` present
     */
    public function withContent()
    {
        $sc = $this->consumer()->sourcesContent;

        return !empty($sc);
    }

    /**
     * @param string $string
     * @param string $start
     *
     * @return bool
     */
    public function startWith($string, $start)
    {
        return is_string($string) && $string !== '' && substr($string, 0, strlen($start)) === $start;
    }

    /**
     * @param string $css
     */
    public function loadAnnotation($css)
    {
        if (preg_match('/\/\*\s*# sourceMappingURL=(.*)\s*\*\//', $css, $match)) {
            $this->annotation = trim($match[1]);
        }
    }

    /**
     * @param string $text
     *
     * @throws Exception\UnsupportedSourceMapEncoding
     *
     * @return string
     */
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

    /**
     * @param string $file
     * @param mixed $prev
     *
     * @throws Exception\UnableToLoadPreviousSourceMap
     * @throws Exception\UnsupportedPreviousSourceMapFormat
     *
     * @return string|false
     */
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
            } elseif ($prev instanceof SourceMapConsumer) {
                return (string) SourceMapGenerator::fromSourceMap($prev);
            } elseif ($prev instanceof SourceMapGenerator) {
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

    /**
     * @param array|mixed $map
     *
     * @return bool
     */
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
