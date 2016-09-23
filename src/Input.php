<?php

namespace PostCSS;

/**
 * Represents the source CSS.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/input.es6
 *
 * @example
 * $root  = \PostCSS\Parser::parse($css, ['from' => $file]);
 * $input = $root->source->input;
 *
 * @property string $from The CSS source identifier. Contains Input->file if the user set the `from` option, or Input->id if they did not
 *
 * @example
 * $root = \PostCSS\Parser::parse($css, ['from' => 'a.css']);
 * $root-source->input->from //=> "/home/ai/a.css"
 * $root = \PostCSS\Parser::parse($css);
 * $root-source->input->from //=> "<input css 1>"
 */
class Input
{
    protected static $sequence = 0;

    /**
     * @var string
     */
    public $css;

    /**
     * @var string|null
     */
    public $file = null;

    /**
     * @var PreviousMap|null
     */
    public $map = null;

    /**
     * @var string|null
     */
    public $id = null;

    /**
     * @param string $css Input CSS source
     * @param array $opts Options (see Processor->process ). It may also contain: {
     *
     *     @var string $from The absolute path to the CSS source file.
     * }
     */
    public function __construct($css, array $opts = [])
    {
        if (is_resource($css)) {
            $s = [];
            while (!feof($css)) {
                $chunk = @stream_get_contents($css, 8192);
                if ($chunk === false) {
                    throw new \Exception('Failed to read from stream');
                }
                $s[] = $chunk;
            }
            $css = implode('', $s);
        } else {
            $css = (string) $css;
        }
        if (isset($css[2]) && $css[0] === "\xEF" && $css[1] === "\xBB" && $css[2] === "\xBF") {
            $css = substr($css, 3);
        }
        $this->css = $css;
        if (isset($opts['from']) && $opts['from']) {
            if (preg_match('/^\w+:\/\//', $opts['from'])) {
                $this->file = $opts['from'];
            } else {
                $this->file = Path\NodeJS::resolve($opts['from']);
            }
        }
        $map = new PreviousMap($this->css, $opts);
        if (isset($map->text)) {
            $this->map = $map;
            $file = $map->consumer()->file;
            if ($this->file === null && $file !== null && $file !== '') {
                $this->file = $this->mapResolve($file);
            }
        }
        if ($this->file === null) {
            ++self::$sequence;
            $this->id = '<input css '.self::$sequence.'>';
        }
        if ($this->map !== null) {
            $this->map->file = $this->from;
        }
    }

    /**
     * Build a CssSyntaxError error.
     *
     * @param string $message
     * @param int $line
     * @param int $column
     * @param array $opts
     *
     * @return Exception\CssSyntaxError
     */
    public function error($message, $line, $column, array $opts = [])
    {
        $origin = $this->origin($line, $column);
        if ($origin !== null) {
            $result = new Exception\CssSyntaxError($message, $origin['line'], $origin['column'], $origin['source'], $origin['file'], isset($opts['plugin']) ? $opts['plugin'] : '');
        } else {
            $result = new Exception\CssSyntaxError($message, $line, $column, $this->css, $this->file, isset($opts['plugin']) ? $opts['plugin'] : '');
        }
        $result->input = [
            'line' => $line,
            'column' => $column,
            'source' => $this->css,
        ];
        if ($this->file !== null) {
            $result->input['file'] = $this->file;
        }

        return $result;
    }

    /**
     * Reads the input source map and returns a symbol position in the input source (e.g., in a Sass file that was compiled to CSS before being passed to PostCSS).
     *
     * @param int $line Line in input CSS
     * @param int $column Column in input CSS
     *
     * @return null|array {
     *
     *     @var string $file Path to file
     *     @var int $line Source line in file
     *     @var int $column Source column in file
     *     @var string $source Contents source (empty if not available).
     * }
     */
    public function origin($line, $column)
    {
        if ($this->map === null) {
            return null;
        }
        $consumer = $this->map->consumer();
        $from = $consumer->originalPositionFor(['line' => $line, 'column' => $column]);
        if ($from === null || $from['source'] === '') {
            return null;
        }
        $result = [
            'file' => $this->mapResolve($from['source']),
            'line' => $from['line'],
            'column' => $from['column'],
            'source' => '',
        ];
        $source = $consumer->sourceContentFor($from['source']);
        if ($source !== null) {
            $result['source'] = $source;
        }

        return $result;
    }

    /**
     * @param string $file
     *
     * @return string
     */
    protected function mapResolve($file)
    {
        if (preg_match('/^\w+:\/\//', $file)) {
            return $file;
        } else {
            return Path\NodeJS::resolve($this->map->consumer()->sourceRoot ?: '.', $file);
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'from':
                return $this->file ?: $this->id;
            default:
                throw new Exception\UndefinedProperty($this, $name);
        }
    }
}
