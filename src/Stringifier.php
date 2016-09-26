<?php

namespace PostCSS;

/**
 * @link https://github.com/postcss/postcss/blob/master/lib/stringifier.es6
 * @link https://github.com/postcss/postcss/blob/master/lib/stringify.es6
 */
class Stringifier
{
    /**
     * @var callable
     */
    protected $builder;

    /**
     * @var callable|null
     */
    protected $stringifier;

    protected static $defaultRaw = [
        'colon' => ': ',
        'indent' => '    ',
        'beforeDecl' => "\n",
        'beforeRule' => "\n",
        'beforeOpen' => ' ',
        'beforeClose' => "\n",
        'beforeComment' => "\n",
        'after' => "\n",
        'emptyBody' => '',
        'commentLeft' => ' ',
        'commentRight' => ' ',
    ];

    /**
     * @param string $str
     *
     * @return string
     */
    protected static function capitalize($str)
    {
        $str = (string) $str;
        if (isset($str[0])) {
            if (isset($str[1])) {
                $str = strtoupper($str[0]).substr($str, 1);
            } else {
                $str = strtoupper($str);
            }
        }

        return $str;
    }

    /**
     * @param callable|null $builder
     * @param callable|null $stringifier
     */
    public function __construct(callable $builder = null, callable $stringifier = null)
    {
        $this->builder = $builder;
        $this->stringifier = $stringifier;
    }

    /**
     * @param Node $node
     * @param bool $semicolon
     */
    public function stringify(Node $node, $semicolon = null)
    {
        if ($this->stringifier !== null) {
            return call_user_func($this->stringifier, $node, $this->builder);
        } else {
            $nodeType = $node->type;

            return $this->$nodeType($node, $semicolon);
        }
    }

    /**
     * @param Node $node
     */
    protected function root(Node $node)
    {
        $this->body($node);
        $after = $node->raws->after;
        if ($after !== null) {
            call_user_func($this->builder, $after);
        }
    }

    /**
     * @param Node $node
     */
    protected function comment(Node $node)
    {
        $left = $this->raw($node, 'left', 'commentLeft');
        $right = $this->raw($node, 'right', 'commentRight');
        call_user_func($this->builder, '/*'.$left.$node->text.$right.'*/', $node);
    }

    /**
     * @param Node $node
     * @param bool $semicolon
     */
    protected function decl(Node $node, $semicolon)
    {
        $between = $this->raw($node, 'between', 'colon');
        $string = $node->prop.$between.$this->rawValue($node, 'value');
        if (isset($node->important) && $node->important) {
            $string .= $node->raws->important ?: ' !important';
        }
        if ($semicolon) {
            $string .= ';';
        }
        call_user_func($this->builder, $string, $node);
    }

    /**
     * @param Node $node
     */
    protected function rule(Node $node)
    {
        $this->block($node, $this->rawValue($node, 'selector'));
    }

    /**
     * @param Node $node
     * @param bool $semicolon
     */
    protected function atrule(Node $node, $semicolon)
    {
        $name = '@'.$node->name;
        $params = isset($node->params) ? $this->rawValue($node, 'params') : '';

        $afterName = $node->raws->afterName;
        if ($afterName !== null) {
            $name .= $afterName;
        } elseif ($params) {
            $name .= ' ';
        }
        if (isset($node->nodes)) {
            $this->block($node, $name.$params);
        } else {
            $end = $node->raws->between.($semicolon ? ';' : '');
            call_user_func($this->builder, $name.$params.$end, $node);
        }
    }

    /**
     * @param Node $node
     */
    protected function body(Node $node)
    {
        $nodeNodesCount = count($node->nodes);
        $last = $nodeNodesCount - 1;
        while ($last > 0) {
            if ($node->nodes[$last]->type !== 'comment') {
                break;
            }
            --$last;
        }
        $semicolon = $this->raw($node, 'semicolon');
        for ($i = 0; $i < $nodeNodesCount; ++$i) {
            $child = $node->nodes[$i];
            $before = $this->raw($child, 'before');
            if ($before !== null && $before !== '') {
                call_user_func($this->builder, $before);
            }
            $this->stringify($child, $last !== $i || $semicolon);
        }
    }

    /**
     * @param Node $node
     * @param string $start
     */
    protected function block(Node $node, $start)
    {
        $between = $this->raw($node, 'between', 'beforeOpen');
        call_user_func($this->builder, $start.$between.'{', $node, 'start');

        if (!empty($node->nodes)) {
            $this->body($node);
            $after = $this->raw($node, 'after');
        } else {
            $after = $this->raw($node, 'after', 'emptyBody');
        }
        if ($after !== null && $after !== '') {
            call_user_func($this->builder, $after);
        }
        call_user_func($this->builder, '}', $node, 'end');
    }

    /**
     * @param Node $node
     * @param string $own
     * @param string|null $detect
     *
     * @return mixed
     */
    public function raw(Node $node, $own, $detect = null)
    {
        if ($detect === null) {
            $detect = $own;
        }

        // Already had
        if ($own) {
            $value = $node->raws->$own;
            if ($value !== null) {
                return $value;
            }
        }
        $parent = $node->parent;

        // Hack for first rule in CSS
        if ($detect === 'before') {
            if ($parent === null || $parent->type === 'root' && $parent->first === $node) {
                return '';
            }
        }

        // Floating child without parent
        if ($parent === null) {
            return isset(static::$defaultRaw[$detect]) ? static::$defaultRaw[$detect] : null;
        }

        // Detect style by other nodes
        $root = $node->root();
        if (!isset($root->rawCache)) {
            $root->rawCache = [];
        }
        if (isset($root->rawCache[$detect])) {
            return $root->rawCache[$detect];
        }
        $value = null;
        if ($detect === 'before' || $detect === 'after') {
            return $this->beforeAfter($node, $detect);
        } else {
            $method = 'raw'.static::capitalize($detect);
            if (is_callable([$this, $method])) {
                $value = $this->$method($root, $node);
            } else {
                $root->walk(function ($i) use (&$value, $own) {
                    $ownValue = $i->raws->$own;
                    if ($ownValue !== null) {
                        $value = $ownValue;

                        return false;
                    }
                });
            }
        }
        if ($value === null && isset(static::$defaultRaw[$detect])) {
            $value = static::$defaultRaw[$detect];
        }
        $root->rawCache[$detect] = $value;

        return $value;
    }

    /**
     * @param Node $root
     *
     * @return bool|null
     */
    protected function rawSemicolon(Node $root)
    {
        $value = null;
        $root->walk(function ($i) use (&$value) {
            if (!empty($i->nodes) && $i->last->type === 'decl') {
                $semiColon = $i->raws->semicolon;
                if ($semiColon !== null) {
                    $value = $semiColon;

                    return false;
                }
            }
        });

        return $value;
    }

    /**
     * @param Node $root
     *
     * @return string|null
     */
    protected function rawEmptyBody(Node $root)
    {
        $value = null;
        $root->walk(function ($i) use (&$value) {
            if (isset($i->nodes) && empty($i->nodes)) {
                $after = $i->raws->after;
                if ($after !== null) {
                    $value = $after;

                    return false;
                }
            }
        });

        return $value;
    }

    /**
     * @param Node $root
     *
     * @return string|null
     */
    protected function rawIndent(Node $root)
    {
        $indent = $root->raws->indent;
        if ($indent !== null) {
            return $indent;
        }
        $value = null;
        $root->walk(function ($i) use (&$value, $root) {
            $p = $i->parent;
            if ($p !== null && $p !== $root && $p->parent !== null && $p->parent === $root) {
                $before = $i->raws->before;
                if ($before !== null) {
                    $parts = explode("\n", $before);
                    $value = preg_replace('/[^\s]/', '', array_pop($parts));

                    return false;
                }
            }
        });

        return $value;
    }

    /**
     * @param Container $root
     * @param Node $node
     *
     * @return string|null
     */
    protected function rawBeforeComment(Container $root, Node $node)
    {
        $value = null;
        $root->walkComments(function ($i) use (&$value) {
            $before = $i->raws->before;
            if ($before !== null) {
                $value = $before;
                if (strpos($value, "\n") !== false) {
                    $value = preg_replace('/[^\n]+$/', '', $value);
                }

                return false;
            }
        });
        if ($value === null) {
            $value = $this->raw($node, null, 'beforeDecl');
        }

        return $value;
    }

    /**
     * @param Container $root
     * @param Node $node
     *
     * @return string|null
     */
    protected function rawBeforeDecl(Container $root, Node $node)
    {
        $value = null;
        $root->walkDecls(function ($i) use (&$value) {
            $before = $i->raws->before;
            if ($before !== null) {
                $value = $before;
                if (strpos($value, "\n") !== false) {
                    $value = preg_replace('/[^\n]+$/', '', $value);
                }

                return false;
            }
        });
        if ($value === null) {
            $value = $this->raw($node, null, 'beforeRule');
        }

        return $value;
    }

    /**
     * @param Container $root
     *
     * @return string|null
     */
    protected function rawBeforeRule(Container $root)
    {
        $value = null;
        $root->walk(function ($i) use ($root, &$value) {
            if (isset($i->nodes) && ($i->parent !== $root || $root->first !== $i)) {
                $before = $i->raws->before;
                if ($before !== null) {
                    $value = $before;
                    if (strpos($value, "\n") !== false) {
                        $value = preg_replace('/[^\n]+$/', '', $value);
                    }

                    return false;
                }
            }
        });

        return $value;
    }

    /**
     * @param Container $root
     *
     * @return string|null
     */
    protected function rawBeforeClose(Container $root)
    {
        $value = null;
        $root->walk(function ($i) use (&$value) {
            if (!empty($i->nodes)) {
                $after = $i->raws->after;
                if ($after !== null) {
                    $value = $after;
                    if (strpos($value, "\n") !== false) {
                        $value = preg_replace('/[^\n]+$/', '', $value);
                    }

                    return false;
                }
            }
        });

        return $value;
    }

    /**
     * @param Container $root
     *
     * @return string|null
     */
    protected function rawBeforeOpen(Container $root)
    {
        $value = null;
        $root->walk(function ($i) use (&$value) {
            if ($i->type !== 'decl') {
                $between = $i->raws->between;
                if ($i->raws->between !== null) {
                    $value = $between;

                    return false;
                }
            }
        });

        return $value;
    }

    /**
     * @param Container $root
     *
     * @return string|null
     */
    protected function rawColon(Container $root)
    {
        $value = null;
        $root->walkDecls(function ($i) use (&$value) {
            $between = $i->raws->between;
            if ($between !== null) {
                $value = preg_replace('/[^\s:]/', '', $between);

                return false;
            }
        });

        return $value;
    }

    /**
     * @param Node $node
     * @param string $detect
     *
     * @return string
     */
    protected function beforeAfter(Node $node, $detect)
    {
        if ($node->type === 'decl') {
            $value = $this->raw($node, null, 'beforeDecl');
        } elseif ($node->type === 'comment') {
            $value = $this->raw($node, null, 'beforeComment');
        } elseif ($detect === 'before') {
            $value = $this->raw($node, null, 'beforeRule');
        } else {
            $value = $this->raw($node, null, 'beforeClose');
        }

        $buf = $node->parent;
        $depth = 0;
        while ($buf !== null && $buf->type !== 'root') {
            $depth += 1;
            $buf = $buf->parent;
        }

        if (strpos($value, "\n") !== false) {
            $indent = $this->raw($node, null, 'indent');
            if ($indent !== '') {
                for ($step = 0; $step < $depth; ++$step) {
                    $value .= $indent;
                }
            }
        }

        return $value;
    }

    /**
     * @param Node $node
     * @param string $prop
     *
     * @return mixed|null
     */
    public function rawValue(Node $node, $prop)
    {
        $value = isset($node->$prop) ? $node->$prop : null;
        $raw = $node->raws->$prop;
        if ($raw !== null) {
            if (is_array($raw) && isset($raw['value']) && $raw['value'] === $value) {
                return isset($raw['raw']) ? $raw['raw'] : null;
            }
            if (is_object($raw) && isset($raw->value) && $raw->value === $value) {
                return isset($raw->raw) ? $raw->raw : null;
            }
        }

        return $value;
    }
}
