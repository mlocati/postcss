<?php

namespace PostCSS;

/**
 * All node classes inherit the following common methods.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/node.es6
 */
class Node
{
    /**
     * Possible values are `root`, `atrule`, `rule`, `decl`, or `comment`.
     *
     * @var string|null
     */
    public $type = null;

    /**
     * The node's parent node.
     *
     * @var Container|null
     */
    public $parent = null;

    /**
     * The input source of the node.
     *
     * @var array|null
     */
    public $source = null;

    public $rawCache = null;

    /**
     * Information to generate byte-to-byte equal node string as it was in the origin input.
     * Every parser saves its own properties, but the default CSS parser uses:
     * - AtRule:
     *   - `before`: the space symbols before the node. It also stores `*` and `_` symbols before the declaration (IE hack).
     *   - `after`: the space symbols after the last child of the node to the end of the node.
     *   - `between`: the symbols between the property and value for declarations, selector and `{` for rules, or last parameter and `{` for at-rules.
     *   - `semicolon`: contains true if the last child has an (optional) semicolon.
     *   - `afterName`: the space between the at-rule name and its parameters.
     * - Comment:
     *   - `before`: the space symbols before the node.
     *   - `left`: the space symbols between `/*` and the comment's text.
     *   - `right`: the space symbols between the comment's text.
     * - Root:
     *   - `after`: the space symbols after the last child to the end of file.
     *   - `semicolon`: is the last child has an (optional) semicolon.
     * - Rule:
     *   - `before`: the space symbols before the node. It also stores `*` and `_` symbols before the declaration (IE hack).
     *   - `after`: the space symbols after the last child of the node to the end of the node.
     *   - `between`: the symbols between the property and value for declarations, selector and `{` for rules, or last parameter and `{` for at-rules.
     *   - `semicolon`: contains true if the last child has an (optional) semicolon.
     *
     * PostCSS cleans at-rule parameters from comments and extra spaces, but it stores origin content in raws properties.
     * As such, if you don't change a declaration's value, PostCSS will use the raw value with comments.
     *
     * @example
     * $root = \PostCSS\Parser::parse('  @media\nprint {\n}');
     * $root->first->first->raws //=> ['before' => '  ', 'between' => ' ', 'afterName' = > "\n", 'after' => "\n"]
     * @example
     * \PostCSS\Parser::parse('a {}\n').raws //=> ['after' => "\n"]
     * \PostCSS\Parser::parse('a {}').raws   //=> ['after => '']
     * @example
     * $root = \PostCSS\Parser::parse('a {\n  color:black\n}');
     * $root->first->first->raws //=> ['before' => '', 'between' => ' ', 'after' => "\n"]
     *
     * @var Raws
     */
    public $raws;

    /**
     * @param array $defaults Value for node properties
     */
    public function __construct(array $defaults = [])
    {
        $this->raws = null;
        foreach ($defaults as $name => $value) {
            switch ($name) {
                case 'raws':
                    if (!($value instanceof Raws)) {
                        $value = new Raws($value);
                    }
                    break;
            }
            $this->$name = $value;
        }
        if ($this->raws === null) {
            $this->raws = new Raws();
        }
    }

    /**
     * Returns a CssSyntaxError instance containing the original position of the node in the source, showing line and column numbers and also a small excerpt to facilitate debugging.
     *
     * If present, an input source map will be used to get the original position of the source, even from a previous compilation step (e.g., from Sass compilation).
     *
     * This method produces very useful error messages.
     *
     * @param string $message Error description
     * @param array $opts Options {
     *
     *     @var string $plugin Plugin name that created this error. PostCSS will set it automatically
     *     @var string $word A word inside a node's string that should be highlighted as the source of the error
     *     @var int $index An index inside a node's string that should be highlighted as the source of the error
     * }
     *
     * @return Exception\CssSyntaxError
     *
     * @example
     *   throw $decl->error('Unknown variable '.$name, ['word' => $name]);
     *   // CssSyntaxError: postcss-vars:a.sass:4:3: Unknown variable $black
     *   //   color: $black
     *   // a
     *   //          ^
     *   //   background: white
     * }
     */
    public function error($message, array $opts = [])
    {
        if ($this->source !== null) {
            $pos = $this->positionBy($opts);

            return $this->source['input']->error($message, $pos['line'], $pos['column'], $opts);
        } else {
            return new Exception\CssSyntaxError($message);
        }
    }

    /**
     * This method is provided as a convenience wrapper for Result->warn.
     *
     * @param Result $result The {@link Result} instance that will receive the warning
     * @param string $text Warning message
     * @param array $opts Options {
     *
     *     @var string $plugin Plugin name that created this warning. PostCSS will set it automatically
     *     @var string $word A word inside a node's string that should be highlighted as the source of the warning
     *     @var int} $index An index inside a node's string that should be highlighted as the source of the warning
     * }
     *
     * @return Warning
     *
     * @example
     * * $plugin = new \PostCSS\ClosurePlugin(function($root, $result) {
     *     $root->walkDecls('bad', function ($decl) use ($result) {
     *        $decl->warn($result, 'Deprecated property bad');
     *     });
     *   })
     */
    public function warn($result, $text, array $opts = [])
    {
        return $result->warn($text, $opts + ['node' => $this]);
    }

    /**
     * Removes the node from its parent and cleans the parent properties from the node and its children.
     *
     * @return static Node to make calls chain
     *
     * @example
     * if (strpos($decl->prop, '-webkit-') === 0) {
     *   $decl.remove();
     * }
     */
    public function remove()
    {
        if ($this->parent !== null) {
            $this->parent->removeChild($this);
        }
        $this->parent = null;

        return $this;
    }

    /**
     * Returns a CSS string representing the node.
     *
     * @return string CSS string of this node
     *
     * @example (string) (new \PostCSS\Rule(['selector'] => 'a'])) //=> "a {}"
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Returns a CSS string representing the node.
     *
     * @param Stringifier|callable $stringifier A syntax to use in string generation
     *
     * @return string
     *
     * @example (string) (new \PostCSS\Rule(['selector'] => 'a'])) //=> "a {}"
     */
    public function toString($stringifier = null)
    {
        $result = '';
        if ($stringifier === null) {
            $stringifier = new Stringifier(
                function ($i) use (&$result) {
                    $result .= $i;
                }
            );
        } elseif (!($stringifier instanceof Stringifier)) {
            if (is_array($stringifier) && isset($stringifier['stringify'])) {
                $sr = $stringifier['stringify'];
            } else {
                $sr = $stringifier;
            }
            $stringifier = new Stringifier(
                function ($i) use (&$result) {
                    $result .= $i;
                },
                $sr
            );
        }
        $stringifier->stringify($this);

        return $result;
    }

    public function __clone()
    {
        $props = get_object_vars($this);
        foreach ($props as $name => $value) {
            switch ($name) {
                case 'parent':
                    $this->parent = null;
                    break;
                case 'source':
                    $this->source = $value;
                    break;
                case 'before':
                case 'after':
                case 'between':
                case 'semicolon':
                    unset($this->$name);
                    break;
                case 'raws':
                    if ($value !== null) {
                        $this->raws = clone $value;
                    }
                    break;
                default:
                    if ($value instanceof self) {
                        $clone = clone $value;
                        $clone->parent = null;
                        $this->$name = $clone;
                    } elseif (is_array($value)) {
                        foreach ($value as $i => $v) {
                            if ($v instanceof self) {
                                $clone = clone $v;
                                $clone->parent = $this;
                                $value[$i] = $clone;
                            }
                        }
                        $this->$name = $value;
                    }
                    break;
            }
        }
    }

    /**
     * Returns a clone of the node.
     *
     * The resulting cloned node and its (cloned) children will have a clean parent and code style properties.
     *
     * @param Container $overrides New parent
     * @param array $overrides New properties to override in the clone
     *
     * @return static Clone of the node
     *
     * @example
     * $cloned = $decl->createClone(['prop' => '-moz-'.$decl->prop]);
     * $cloned->raws->before //=> null
     * $cloned->parent       //=> null
     * (string) $cloned      //=> -moz-transform: scale(0)
     */
    public function createClone(array $overrides = [])
    {
        $cloned = clone $this;
        foreach ($overrides as $name => $value) {
            $cloned->$name = $value;
        }

        return $cloned;
    }

    /**
     * Shortcut to clone the node and insert the resulting cloned node before the current node.
     *
     * @param array $overrides New properties to override in the clone
     *
     * @return static New node
     *
     * @example
     * $decl->cloneBefore(['prop' => '-moz-'.$decl->prop]);
     */
    public function cloneBefore(array $overrides = [])
    {
        $cloned = $this->createClone($overrides);
        $this->parent->insertBefore($this, $cloned);

        return $cloned;
    }

    /**
     * Shortcut to clone the node and insert the resulting cloned node after the current node.
     *
     * @param array $overrides New properties to override in the clone
     *
     * @return static New node
     */
    public function cloneAfter(array $overrides = [])
    {
        $cloned = $this->createClone($overrides);
        $this->parent->insertAfter($this, $cloned);

        return $cloned;
    }

    /**
     * Inserts node(s) before the current node and removes the current node.
     *
     * @param ...Node $nodes Node(s) to replace current one
     *
     * @return static Current node to methods chain
     *
     * @example
     * if ($atrule->name === 'mixin' ) {
     *   $atrule->replaceWith($mixinRules[$atrule->params]);
     * }
     */
    public function replaceWith(/*...*/$nodes)
    {
        if ($this->parent !== null) {
            $nodes = func_get_args();
            foreach ($nodes as $node) {
                $this->parent->insertBefore($this, $node);
            }

            $this->remove();
        }

        return $this;
    }

    /**
     * Removes the node from its current parent and inserts it at the end of $newParent.
     *
     * This will clean the `before` and `after` code Node->raws data from the node and replace them with the indentation style of `newParent`.
     * It will also clean the `between` property if `newParent` is in another {@link Root}.
     *
     * @param $Container $newParent Container node where the current node will be moved
     *
     * @return static Current node to methods chain
     *
     * @example
     * $atrule->moveTo($atrule->root());
     */
    public function moveTo(Container $newParent)
    {
        $this->cleanRaws($this->root() === $newParent->root());
        $this->remove();
        $newParent->append($this);

        return $this;
    }

    /**
     * Removes the node from its current parent and inserts it into a new parent before $otherNode.
     *
     * This will also clean the node's code style properties just as it would in Node->moveTo.
     *
     * @param Node $otherNode Node that will be before current node
     *
     * @return static Current node to methods chain
     */
    public function moveBefore(Node $otherNode)
    {
        $this->cleanRaws($this->root() === $otherNode->root());
        $this->remove();
        $otherNode->parent->insertBefore($otherNode, $this);

        return $this;
    }

    /**
     * Removes the node from its current parent and inserts it into a new parent after $otherNode.
     *
     * This will also clean the node's code style properties just as it would in Node->moveTo.
     *
     * @param Node $otherNode Node that will be after current node
     *
     * @return static Current node to methods chain
     */
    public function moveAfter(Node $otherNode)
    {
        $this->cleanRaws($this->root() === $otherNode->root());
        $this->remove();
        $otherNode->parent->insertAfter($otherNode, $this);

        return $this;
    }

    /**
     * Returns the next child of the node's parent.
     * Returns null if the current node is the last child.
     *
     * @return Node|null Next node
     *
     * @example
     * if ($comment->text === 'delete next') {
     *   $next = $comment.next();
     *   if ($next !== null) {
     *     $next->remove();
     *   }
     * }
     */
    public function next()
    {
        $result = null;
        if ($this->parent !== null && isset($this->parent->nodes)) {
            $index = $this->parent->index($this);
            if ($index !== null && isset($this->parent->nodes[$index + 1])) {
                $result = $this->parent->nodes[$index + 1];
            }
        }

        return $result;
    }

    /**
     * Returns the previous child of the node's parent.
     * Returns null if the current node is the first child.
     *
     * @return Node|null Previous node
     *
     * @example
     *  $annotation = $decl->prev();
     *  if ($annotation->type === 'comment' ) {
     *     readAnnotation($annotation->text);
     *  }
     */
    public function prev()
    {
        $result = null;
        if ($this->parent !== null && isset($this->parent->nodes)) {
            $index = $this->parent->index($this);
            if ($index !== null && isset($this->parent->nodes[$index - 1])) {
                $result = $this->parent->nodes[$index - 1];
            }
        }

        return $result;
    }

    /**
     * @param array $parent
     * @param string|int $name
     * @param mixed $value
     */
    private static function jsonify(array &$parent, $name, $value)
    {
        switch (gettype($value)) {
            case 'NULL':
            case 'resource':
                break;
            case 'array':
                $v2 = [];
                foreach ($value as $k => $v) {
                    self::jsonify($v2, $k, $v);
                }
                if ($name === 'nodes' || !empty($v2)) {
                    $parent[$name] = $v2;
                }
                break;
            case 'object':
                if (is_callable([$value, 'toJSON'])) {
                    $v = $value->toJSON();
                    if (!empty($v)) {
                        $parent[$name] = $v;
                    }
                } else {
                    $vars = get_object_vars($value);
                    if (isset($vars['toJSON']) && is_callable($vars['toJSON'])) {
                        $v = call_user_func($vars['toJSON']);
                        if (!empty($v)) {
                            $parent[$name] = $v;
                        }
                    } else {
                        $v2 = [];
                        foreach ($vars as $k => $v) {
                            self::jsonify($v2, $k, $v);
                        }
                        if (!empty($v2)) {
                            $parent[$name] = $v2;
                        }
                    }
                }
                break;
            default:
                if (is_scalar($value)) {
                    $parent[$name] = $value;
                }
                break;
        }
    }

    /**
     * @return array
     */
    public function toJSON()
    {
        $fixed = [];
        foreach (get_object_vars($this) as $name => $value) {
            if ($name === 'parent') {
                continue;
            }
            self::jsonify($fixed, $name, $value);
        }

        return $fixed;
    }

    /**
     * Returns a Node->raws value.
     * If the node is missing the code style property (because the node was manually built or cloned), PostCSS will try to autodetect the code style property by looking at other nodes in the tree.
     *
     * @param string $prop Name of code style property
     * @param string $defaultType Name of default value, it can be missed if the value is the same as prop
     *
     * @return string Code style value
     *
     * @example
     *   $root = \PostCSS\Parser::parse('a { background: white }');
     *   $root->nodes[0]->append(['prop' => 'color', 'value' => 'black']);
     *   $root->nodes[0]->nodes[1]->raws.before   //=> null
     *   $root->nodes[0]->nodes[1]->raw('before') //=> ' '
     */
    public function raw($prop, $defaultType = null)
    {
        $str = new Stringifier();

        return $str->raw($this, $prop, $defaultType);
    }

    /**
     * Finds the Root instance of the node's tree.
     *
     * @return Root root parent
     *
     * @example
     *   $root->nodes[0]->nodes[0]->root() === $root
     */
    public function root()
    {
        $result = $this;
        while ($result->parent !== null) {
            $result = $result->parent;
        }

        return $result;
    }

    /**
     * Unset before/after and (optionally) between from $this->raws.
     *
     * @param bool $keepBetween
     */
    public function cleanRaws($keepBetween = false)
    {
        unset($this->raws->before);
        unset($this->raws->after);
        if (!$keepBetween) {
            unset($this->raws->between);
        }
    }

    /**
     * @param unknown $index
     *
     * @return array {
     *
     *     @var int $line
     *     @var int $column
     * }
     */
    public function positionInside($index)
    {
        $string = (string) $this;
        $column = $this->source['start']['column'];
        $line = $this->source['start']['line'];

        for ($i = 0; $i < $index; ++$i) {
            if (isset($string[$i]) && $string[$i] === "\n") {
                $column = 1;
                $line += 1;
            } else {
                $column += 1;
            }
        }

        return ['line' => $line, 'column' => $column];
    }

    /**
     * @param array $opts
     *
     * @return array|null
     */
    public function positionBy(array $opts)
    {
        $pos = isset($this->source['start']) ? $this->source['start'] : null;
        if (isset($opts['index']) && $opts['index']) {
            $pos = $this->positionInside($opts['index']);
        } elseif (isset($opts['word']) && $opts['word']) {
            $index = strpos((string) $this, $opts['word']);
            if ($index !== false) {
                $pos = $this->positionInside($index);
            }
        }

        return $pos;
    }

    /**
     * @deprecated Use Node->remove
     */
    public function removeSelf()
    {
        return $this->remove();
    }

    /**
     * @deprecated Use Node->replaceWith
     */
    public function replace($nodes)
    {
        return $this->replaceWith($nodes);
    }

    /**
     * @deprecated Use Node->raw()
     */
    public function style($own, $detect)
    {
        return $this->raw($own, $detect);
    }

    /**
     * @deprecated Use Node->cleanRaws
     */
    public function cleanStyles($keepBetween)
    {
        return $this->cleanRaws($keepBetween);
    }

    /**
     * @deprecated Node->before was deprecated. Use Node->raws->before
     * @deprecated Node->between was deprecated. Use Node->raws->between
     */
    public function __get($name)
    {
        switch ($name) {
            case 'before':
                return $this->raws->before;
            case 'between':
                return $this->raws->between;
            default:
                return null;
        }
    }

    /**
     * @deprecated Node->before was deprecated. Use Node->raws->before
     * @deprecated Node->before was deprecated. Use Node->raws->before
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'before':
                $this->raws->before = $value;
                break;
            case 'between':
                $this->raws->between = $value;
                break;
            default:
                $this->$name = $value;
        }
    }
}
