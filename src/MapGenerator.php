<?php

namespace PostCSS;

use PostCSS\Path\NodeJS as Path;
use PostCSS\SourceMap\Consumer\Consumer;

/**
 * @link https://github.com/postcss/postcss/blob/master/lib/map-generator.es6
 */
class MapGenerator
{
    /**
     * @var callable
     */
    protected $stringify;

    /**
     * @var array
     */
    protected $mapOpts;

    /**
     * @var Root
     */
    protected $root;

    /**
     * @var array
     */
    protected $opts;

    /**
     * @var PreviousMap[]|null
     */
    protected $previousMaps = null;

    /**
     * @var string|null
     */
    protected $css = null;

    /**
     * @var SourceMap\Generator|null
     */
    protected $map = null;

    /**
     * @param callable $stringify
     * @param Root $root
     * @param array $opts
     */
    public function __construct(callable $stringify, Root $root, array $opts = [])
    {
        $this->stringify = $stringify;
        $this->mapOpts = isset($opts['map']) ? $opts['map'] : [];
        $this->root = $root;
        $this->opts = $opts;
    }

    /**
     * @return bool
     */
    public function isMap()
    {
        if (isset($this->opts['map'])) {
            return $this->opts['map'] ? true : false;
        } else {
            $prev = $this->previous();

            return !empty($prev);
        }
    }

    /**
     * @return PreviousMap[]
     */
    public function previous()
    {
        if ($this->previousMaps === null) {
            $this->previousMaps = [];
            $me = $this;
            $this->root->walk(function ($node) use ($me) {
                if ($node->source !== null && $node->source['input']->map) {
                    $map = $node->source['input']->map;
                    if (!in_array($map, $me->previousMaps, true)) {
                        $me->previousMaps[] = $map;
                    }
                }
            });
        }

        return $this->previousMaps;
    }

    /**
     * @return bool
     */
    public function isInline()
    {
        if (isset($this->mapOpts['inline'])) {
            return $this->mapOpts['inline'];
        }

        $annotation = isset($this->mapOpts['annotation']) ? $this->mapOpts['annotation'] : null;
        if ($annotation !== null && $annotation !== true) {
            return false;
        }

        $previous = $this->previous();
        if (!empty($previous)) {
            $some = false;
            foreach ($previous as $p) {
                if ($p->inline) {
                    $some = true;
                    break;
                }
            }

            return $some;
        } else {
            return true;
        }
    }

    /**
     * @return bool
     */
    public function isSourcesContent()
    {
        if (isset($this->mapOpts['sourcesContent'])) {
            return $this->mapOpts['sourcesContent'];
        }
        $previous = $this->previous();
        if (!empty($previous)) {
            $some = false;
            foreach ($previous as $p) {
                if ($p->withContent()) {
                    $some = true;
                    break;
                }
            }

            return $some;
        } else {
            return true;
        }
    }

    public function clearAnnotation()
    {
        if (isset($this->mapOpts['annotation']) && $this->mapOpts['annotation'] === false) {
            return;
        }
        for ($i = count($this->root->nodes) - 1; $i >= 0; --$i) {
            $node = $this->root->nodes[$i];
            if (!($node instanceof Comment)) {
                continue;
            }
            if (strpos($node->text, '# sourceMappingURL=') === 0) {
                $this->root->removeChild($i);
            }
        }
    }

    public function setSourcesContent()
    {
        $me = $this;
        $already = [];
        $this->root->walk(function (Node $node) use ($me, &$already) {
            if ($node->source && isset($node->source['input'])) {
                $from = $node->source['input']->from;
                if ($from && !isset($already[$from])) {
                    $already[$from] = true;
                    $relative = $me->relative($from);
                    $me->map->setSourceContent($relative, $node->source['input']->css);
                }
            }
        });
    }

    public function applyPrevMaps()
    {
        foreach ($this->previous() as $prev) {
            $from = $this->relative($prev->file);
            $root = (isset($prev->root) && $prev->root) ? $prev->root : Path::dirname($prev->file);
            if (isset($this->mapOpts['sourcesContent']) && $this->mapOpts['sourcesContent'] === false) {
                $map = Consumer::construct($prev->text);
                if ($map->sourcesContent) {
                    $map->sourcesContent = array_fill(0, count($map->sourcesContent), null);
                }
            } else {
                $map = $prev->consumer();
            }

            $this->map->applySourceMap($map, $from, $this->relative($root));
        }
    }

    /**
     * @return bool
     */
    public function isAnnotation()
    {
        if ($this->isInline()) {
            return true;
        } elseif (isset($this->mapOpts['annotation'])) {
            return $this->mapOpts['annotation'];
        } else {
            $previous = $this->previous();
            if (!empty($previous)) {
                $some = false;
                foreach ($previous as $p) {
                    if ($p->annotation) {
                        $some = true;
                        break;
                    }
                }

                return $some;
            } else {
                return true;
            }
        }
    }

    public function addAnnotation()
    {
        if ($this->isInline()) {
            $content = 'data:application/json;base64,'.base64_encode((string) $this->map);
        } elseif (isset($this->mapOpts['annotation']) && is_string($this->mapOpts['annotation'])) {
            $content = $this->mapOpts['annotation'];
        } else {
            $content = $this->outputFile().'.map';
        }

        $eol = "\n";
        if (strpos($this->css, "\r\n") !== false) {
            $eol = "\r\n";
        }

        $this->css .= $eol.'/*# sourceMappingURL='.$content.' */';
    }

    /**
     * @return string
     */
    public function outputFile()
    {
        if (isset($this->opts['to']) && $this->opts['to']) {
            return $this->relative($this->opts['to']);
        } elseif (isset($this->opts['from']) && $this->opts['from']) {
            return $this->relative($this->opts['from']);
        } else {
            return 'to.css';
        }
    }

    /**
     * @return array First array item is the css. If not inline, there's a second item (a SourceMap\Generator instance)
     */
    public function generateMap()
    {
        $this->generateString();
        if ($this->isSourcesContent()) {
            $this->setSourcesContent();
        }
        $previous = $this->previous();
        if (!empty($previous)) {
            $this->applyPrevMaps();
        }
        if ($this->isAnnotation()) {
            $this->addAnnotation();
        }

        if ($this->isInline()) {
            return [$this->css];
        } else {
            return [$this->css, $this->map];
        }
    }

    /**
     * @param string $file
     *
     * @return string
     */
    public function relative($file)
    {
        if (preg_match('/^\w+:\/\//', $file)) {
            return $file;
        }

        $from = (isset($this->opts['to']) && $this->opts['to']) ? Path::dirname($this->opts['to']) : '.';

        if (isset($this->mapOpts['annotation']) && is_string($this->mapOpts['annotation'])) {
            $from = Path::dirname(Path::resolve($from, $this->mapOpts['annotation']));
        }

        $file = Path::relative($from, $file);
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_replace('\\', '/', $file);
        } else {
            return $file;
        }
    }

    /**
     * @param Node $node
     *
     * @return string
     */
    public function sourcePath(Node $node)
    {
        if (isset($this->mapOpts['from']) && $this->mapOpts['from']) {
            return $this->mapOpts['from'];
        } else {
            return $this->relative($node->source['input']->from);
        }
    }

    public function generateString()
    {
        $this->css = '';
        $this->map = new SourceMap\Generator(['file' => $this->outputFile()]);

        $line = 1;
        $column = 1;

        $me = $this;
        call_user_func(
            $this->stringify,
            $this->root,
            function ($str, Node $node = null, $type = null) use ($me, &$line, &$column) {
                $me->css .= $str;
                if ($node !== null && $type !== 'end') {
                    if ($node->source && isset($node->source['start']) && $node->source['start']) {
                        $me->map->addMapping([
                            'source' => $me->sourcePath($node),
                            'generated' => [
                                'line' => $line,
                                'column' => $column - 1,
                            ],
                            'original' => [
                                'line' => $node->source['start']['line'],
                                'column' => $node->source['start']['column'] - 1,
                            ],
                        ]);
                    } else {
                        $me->map->addMapping([
                        'source' => '<no source>',
                        'original' => [
                            'line' => 1,
                            'column' => 0,
                        ],
                        'generated' => [
                            'line' => $line,
                            'column' => $column - 1,
                        ],
                    ]);
                    }
                }

                if (preg_match('/\n/', $str, $lines)) {
                    $line += count($lines);
                    $last = strrpos($str, "\n");
                    $column = strlen($str) - $last;
                } else {
                    $column += strlen($str);
                }

                if ($node && $type !== 'start') {
                    if ($node->source && isset($node->source['end']) && $node->source['end']) {
                        $me->map->addMapping([
                            'source' => $me->sourcePath($node),
                            'generated' => [
                                'line' => $line,
                                'column' => $column - 1,
                            ],
                            'original' => [
                                'line' => $node->source['end']['line'],
                                'column' => $node->source['end']['column'],
                            ],
                        ]);
                    } else {
                        $me->map->addMapping([
                            'source' => '<no source>',
                            'original' => [
                                'line' => 1,
                                'column' => 0,
                            ],
                            'generated' => [
                                'line' => $line,
                                'column' => $column - 1,
                            ],
                        ]);
                    }
                }
            }
        );
    }

    /**
     * @return array First array item is the css. If not inline, there's a second item (a SourceMap\Generator instance)
     */
    public function generate()
    {
        $this->clearAnnotation();

        if ($this->isMap()) {
            return $this->generateMap();
        } else {
            $result = '';
            call_user_func(
                $this->stringify,
                $this->root,
                function ($i) use (&$result) {
                    $result .= $i;
                }
            );

            return [$result];
        }
    }
}
