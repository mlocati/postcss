<?php

namespace PostCSS;

/**
 * The Root, AtRule, and Rule container nodes inherit some common methods to help work with their children.
 *
 * Note that all containers can store any content. If you write a rule inside a rule, PostCSS will parse it.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/container.es6
 *
 * @property Node|null $first The container’s first child (@example $rule->first == $rules->nodes[0]) )
 * @property Node|null $last The container’s last child (@example $rule->last == $rules->nodes[count($rules->nodes) - 1] )
 */
abstract class Container extends Node
{
    private $lastEach = null;
    private $indexes = null;
    public $nodes = null;

    /**
     * @param Node $child
     *
     * @return static
     */
    public function push(Node $child)
    {
        $child->parent = $this;
        $this->nodes[] = $child;

        return $this;
    }

    /**
     * Iterates through the container's immediate children, calling `callback` for each child.
     *
     * Returning `false` in the callback will break iteration.
     *
     * This method only iterates through the container's immediate children.
     * If you need to recursively iterate through all the container's descendant nodes, use Container->walk.
     *
     * @param callable $callback It'll receive a Node instance and its index (int). If it returns false the iteration will be aborted
     *
     * @return null|false Returns `false` if iteration was aborted
     *
     * @example
     * $root = \PostCSS\Parser::parse('a { color: black; z-index: 1 }');
     * $rule = $root->first;
     *
     * foreach ($rule->nodes as $decl) {
     *     $decl->cloneBefore(['prop' = > '-webkit-'.$decl->prop]);
     *     // Cycle will be infinite, because cloneBefore moves the current node to the next index
     * }
     *
     * $rule->each(function($decl) {
     *     $decl->cloneBefore(['prop' = > '-webkit-'.$decl->prop]);
     *     // Will be executed only for color and z-index
     * });
     */
    public function each(callable $callback)
    {
        if ($this->lastEach === null) {
            $this->lastEach = 0;
        }
        if ($this->indexes === null) {
            $this->indexes = [];
        }
        $this->lastEach += 1;
        $id = $this->lastEach;
        $this->indexes[$id] = 0;
        if (empty($this->nodes)) {
            return null;
        }
        $index = null;
        $result = null;
        while ($this->indexes[$id] < count($this->nodes)) {
            $index = $this->indexes[$id];
            $result = $callback($this->nodes[$index], $index);
            if ($result === false) {
                break;
            }
            $this->indexes[$id] += 1;
        }

        unset($this->indexes[$id]);

        return $result;
    }

    /**
     * Traverses the container's descendant nodes, calling callback for each node.
     *
     * Like Container::each(), this method is safe to use if you are mutating arrays during iteration.
     *
     * If you only need to iterate through the container's immediate children, use Container->each.
     *
     * @param callable $callback It'll receive a Node instance and its index (int). If it returns false the iteration will be aborted
     *
     * @return null|false Returns `false` if iteration was aborted
     *
     * @example
     * $root->walk(function($node) {
     *   // Traverses all descendant nodes.
     * });
     */
    public function walk(callable $callback)
    {
        return $this->each(function ($child, $i) use ($callback) {
            $result = $callback($child, $i);
            if ($result !== false && is_callable([$child, 'walk'])) {
                $result = $child->walk($callback);
            }

            return $result;
        });
    }

    /**
     * Traverses the container's descendant nodes, calling callback for each declaration node.
     *
     * If you pass a filter, iteration will only happen over declarations with matching properties.
     *
     * Like Container->each, this method is safe to use if you are mutating arrays during iteration.
     *
     * @param string $prop String (it may be also be a regular expression with the `/` terminator) to filter declarations by property name
     * @param callable $callback It'll receive a Node instance and its index (int). If it returns false the iteration will be aborted
     *
     * @return null|false Returns `false` if iteration was aborted
     *
     * @example
     * $root->walkDecls(function ($decl) {
     *   checkPropertySupport($decl->prop);
     * });
     *
     * $root->walkDecls('border-radius', function ($decl) {
     *   $decl->remove();
     * });
     *
     * $root->walkDecls('/^background/', function ($decl) {
     *   $decl->value = takeFirstColorFromGradient($decl->value);
     * });
     */
    public function walkDecls($prop, callable $callback = null)
    {
        if ($callback === null) {
            $callback = $prop;

            return $this->walk(function ($child, $i) use ($callback) {
                if (isset($child->type) && $child->type === 'decl') {
                    return $callback($child, $i);
                }
            });
        } elseif (is_string($prop) && isset($prop[1]) && $prop[0] === '/' && preg_match('_^/.*/[a-zA-Z]*$_', $prop)) {
            return $this->walk(function ($child, $i) use ($prop, $callback) {
                if (isset($child->type) && isset($child->prop) && $child->type === 'decl' && preg_match($prop, $child->prop)) {
                    return $callback($child, $i);
                }
            });
        } else {
            return $this->walk(function ($child, $i) use ($prop, $callback) {
                if (isset($child->type) && isset($child->prop) && $child->type === 'decl' && $child->prop === $prop) {
                    return $callback($child, $i);
                }
            });
        }
    }

    /**
     * Traverses the container's descendant nodes, calling callback for each rule node.
     *
     * If you pass a filter, iteration will only happen over rules with matching selectors.
     *
     * Like Container->each, this method is safe to use if you are mutating arrays during iteration.
     *
     * @param string $selector String (it may be also be a regular expression with the `/` terminator) to filter rules by selector
     * @param callable $callback It'll receive a Node instance and its index (int). If it returns false the iteration will be aborted
     *
     * @return null|false Returns `false` if iteration was aborted
     *
     * @example
     * $selectors = [];
     * $root->walkRules(function ($rule) use (&$selectors) {
     *   $selectors[] = $rule->selector;
     * });
     * echo 'Your CSS uses '.count($selectors).' selectors');
     */
    public function walkRules($selector, callable $callback = null)
    {
        if ($callback === null) {
            $callback = $selector;

            return $this->walk(function ($child, $i) use ($callback) {
                if ($child->type === 'rule') {
                    return $callback($child, $i);
                }
            });
        } elseif (is_string($selector) && isset($selector[1]) && $selector[0] === '/' && preg_match('_^/.*/[a-zA-Z]*$_', $selector)) {
            return $this->walk(function ($child, $i) use ($callback, $selector) {
                if (isset($child->selector) && $child->type === 'rule' && preg_match($selector, $child->selector)) {
                    return $callback($child, $i);
                }
            });
        } else {
            return $this->walk(function ($child, $i) use ($callback, $selector) {
                if (isset($child->selector) && $child->type === 'rule' && $child->selector === $selector) {
                    return $callback($child, $i);
                }
            });
        }
    }

    /**
     * Traverses the container's descendant nodes, calling callback for each at-rule node.
     *
     * If you pass a filter, iteration will only happen over at-rules that have matching names.
     *
     * Like Container->each, this method is safe to use if you are mutating arrays during iteration.
     *
     * @param string $selector String (it may be also be a regular expression with the `/` terminator) to filter at-rules by name
     * @param callable $callback It'll receive a Node instance and its index (int). If it returns false the iteration will be aborted
     *
     * @return null|false Returns `false` if iteration was aborted
     *
     * @example
     * $root->walkAtRules(function ($rule) {
     *   if (isOld($rule->name)) {
     *     $rule->remove();
     *   }
     * });
     *
     * $first = false;
     * $root->walkAtRules('charset', function ($rule) use (&$first) {
     *   if (!$first) {
     *     $first = true;
     *   } else {
     *     $rule->remove();
     *   }
     * });
     */
    public function walkAtRules($name, callable $callback = null)
    {
        if ($callback === null) {
            $callback = $name;

            return $this->walk(function ($child, $i) use ($callback) {
                if ($child->type === 'atrule') {
                    return $callback($child, $i);
                }
            });
        } elseif (is_string($name) && isset($name[1]) && $name[0] === '/' && preg_match('_^/.*/[a-zA-Z]*$_', $name)) {
            return $this->walk(function ($child, $i) use ($callback, $name) {
                if (isset($child->name) && $child->type === 'atrule' && preg_match($name, $child->name)) {
                    return $callback($child, $i);
                }
            });
        } else {
            return $this->walk(function ($child, $i) use ($callback, $name) {
                if (isset($child->name) && $child->type === 'atrule' && $child->name === $name) {
                    return $callback($child, $i);
                }
            });
        }
    }

    /**
     * Traverses the container's descendant nodes, calling callback for each comment node.
     *
     * Like Container->each, this method is safe to use if you are mutating arrays during iteration.
     *
     * @param callable $callback It'll receive a Node instance and its index (int). If it returns false the iteration will be aborted
     *
     * @return null|false Returns `false` if iteration was aborted
     *
     * @example
     * $root->walkComments(function ($comment) {
     *   $comment->remove();
     * });
     */
    public function walkComments(callable $callback)
    {
        return $this->walk(function ($child, $i) use ($callback) {
            if ($child->type === 'comment') {
                return $callback($child, $i);
            }
        });
    }

    /**
     * Inserts new nodes to the start of the container.
     *
     * @param ...(Node|object|string|Node[]) $children New nodes
     *
     * @return static This node for methods chain
     *
     * @example
     * $decl1 = new \PostCSS\Declaration(['prop' => 'color', 'value' = >'black']);
     * $decl2 = new \PostCSS\Declaration(['prop' => 'background-color', 'value' => 'white']);
     * $rule->append($decl1, $decl2);
     *
     * $root->append(['name' => 'charset', 'params' => '"UTF-8"']); // at-rule
     * $root->append(['selector' => 'a']);                          // rule
     * $rule->append(['prop' => 'color', 'value' => 'black']);      // declaration
     * $rule->append(['text' => 'Comment'])                         // comment
     *
     * $root->append('a {}');
     * $root->first->append('color: black; z-index: 1');
     */
    public function append(/*...*/$children)
    {
        $children = func_get_args();
        $last = $this->last;
        foreach ($children as $child) {
            $nodes = $this->normalize($child, $last);
            if (!empty($nodes)) {
                $this->nodes = array_merge($this->nodes, $nodes);
                $last = array_pop($nodes);
            }
        }

        return $this;
    }

    /**
     * Inserts new nodes to the end of the container.
     *
     * @param ...(Node|object|string|Node[]) $children New nodes
     *
     * @return static This node for methods chain
     *
     * @example
     * $decl1 = new \PostCSS\Declaration(['prop' => 'color', value' => 'black']);
     * $decl2 = \PostCSS\Declaration(['prop' => 'background-color', value' => 'white']);
     * $rule->prepend($decl1, $decl2);
     *
     * $root->append(['name' => 'charset', params' => '"UTF-8"']); // at-rule
     * $root->append(['selector' => 'a']);                         // rule
     * $rule->append(['prop' => 'color', value' => 'black']);      // declaration
     * $rule->append(['text' => 'Comment'])                        // comment
     *
     * $root->append('a {}');
     * $root->first->append('color: black; z-index: 1');
     */
    public function prepend(/*...*/$children)
    {
        $children = array_reverse(func_get_args());
        $first = $this->first;
        foreach ($children as $child) {
            $nodes = array_reverse($this->normalize($child, $first, 'prepend'));
            $nodesCount = count($nodes);
            if ($nodesCount > 0) {
                foreach ($nodes as $node) {
                    array_unshift($this->nodes, $node);
                }
                if ($this->indexes !== null) {
                    foreach ($this->indexes as $id => $value) {
                        $this->indexes[$id] = $value + $nodesCount;
                    }
                }
                $first = array_pop($nodes);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see Node::cleanRaws()
     */
    public function cleanRaws($keepBetween = false)
    {
        parent::cleanRaws($keepBetween);
        if ($this->nodes) {
            foreach ($this->nodes as $node) {
                $node->cleanRaws($keepBetween);
            }
        }
    }

    /**
     * Insert new node before old node within the container.
     *
     * @param Node|int $exist Child or child's index
     * @param Node|object|string|Node[] $add New node(s)
     *
     * @return static This node for methods chain
     *
     * @example
     * $rule->insertBefore($decl, $decl->cloneNode(['prop' => '-webkit-'.$decl->prop]));
     */
    public function insertBefore($exist, $add)
    {
        $exist = $this->index($exist);
        $type = ($exist === 0) ? 'prepend' : false;
        $nodes = $this->normalize($add, isset($this->nodes[$exist]) ? $this->nodes[$exist] : null, $type);
        $numNodes = count($nodes);
        if ($numNodes > 0) {
            if ($exist < 0) {
                $this->nodes = array_merge($this->nodes, $nodes);
            } else {
                array_splice($this->nodes, $exist, 0, $nodes);
            }
            if (!empty($this->indexes)) {
                foreach ($this->indexes as $id => $index) {
                    if ($exist <= $index) {
                        $this->indexes[$id] = $index + $numNodes;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Insert new node after old node within the container.
     *
     * @param Node|int $exist Child or child's index
     * @param Node|object|string|Node[] add - new node
     *
     * @return static this node for methods chain
     */
    public function insertAfter($exist, $add)
    {
        $exist = $this->index($exist);
        $nodes = $this->normalize($add, isset($this->nodes[$exist]) ? $this->nodes[$exist] : null);
        $numNodes = count($nodes);
        if ($numNodes > 0) {
            if ($exist < 0) {
                $this->nodes = array_merge($nodes, $this->nodes);
            } else {
                array_splice($this->nodes, $exist + 1, 0, $nodes);
            }
            if (!empty($this->indexes)) {
                foreach ($this->indexes as $id => $index) {
                    if ($exist < $index) {
                        $this->indexes[$id] = $index + $numNodes;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see Node::remove()
     */
    public function remove()
    {
        if (func_num_args() !== 0) {
            throw new \Exception('Container->remove(child) is deprecated. Use Container->removeChild');
        }

        return parent::remove();
    }

    /**
     * Removes node from the container and cleans the parent properties from the node and its children.
     *
     * @param Node|int $child Child or child's index
     *
     * @return static This node for methods chain
     *
     * @example
     * count($rule->nodes)        //=> 5
     * $rule->removeChild($decl);
     * count($rule->nodes)        //=> 4
     * $decl->parent              //=> null
     */
    public function removeChild($child)
    {
        $child = $this->index($child);
        if ($child >= 0) {
            $this->nodes[$child]->parent = null;
            array_splice($this->nodes, $child, 1);
            if (!empty($this->indexes)) {
                foreach ($this->indexes as $id => $index) {
                    if ($index >= $child) {
                        $this->indexes[$id] = $index - 1;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Removes all children from the container and cleans their parent properties.
     *
     * @return static This node for methods chain
     *
     * @example
     * $rule->removeAll();
     * count($rule->nodes) //=> 0
     */
    public function removeAll()
    {
        if ($this->nodes !== null) {
            foreach ($this->nodes as $node) {
                $node->parent = null;
            }
        }
        $this->nodes = [];

        return $this;
    }

    /**
     * Passes all declaration values within the container that match pattern through callback, replacing those values with the returned result of callback.
     *
     * This method is useful if you are using a custom unit or function and need to iterate through all values.
     *
     * @param string $pattern Replace pattern (it may also be a regular expression)
     * @param array $opts Options to speed up the search {
     *
     *     @var string|string[] $props An array of property names
     *     @var string $fast string that's used to narrow down values and speed up the regexp search
     * }
     *
     * @param callable|string $callback String to replace pattern or callback that returns a new value. The callback will receive the same arguments as those passed to a function parameter of `str_replace`/`preg_replace_callback`/`preg_replace`/`call_user_func`/`call_user_func_array`
     *
     * @return static This node for methods chain
     *
     * @example
     * $root->replaceValues('/\d+rem/', ['fast' => 'rem'], function($string) {
     *   return (15 * ((int) $string)).'px';
     * });
     */
    public function replaceValues($pattern, $opts, $callback = null)
    {
        if ($callback === null) {
            $callback = $opts;
            $opts = [];
        }
        $opts += [
            'props' => null,
            'fast' => null,
        ];
        if (is_string($opts['props'])) {
            $opts['props'] = [$opts['props']];
        }
        $patternIsRX = is_string($pattern) && isset($pattern[1]) && $pattern[0] === '/' && preg_match('_^/.*/[a-zA-Z]*$_', $pattern);
        $callbackIsCallable = is_callable($callback);
        $this->walkDecls(function ($decl) use ($opts, $pattern, $patternIsRX, $callback, $callbackIsCallable) {
            if ($opts['props'] && !in_array($decl->prop, $opts['props'])) {
                return;
            }
            if ($opts['fast'] && strpos($decl->value, $opts['fast']) === false) {
                return;
            }
            if ($patternIsRX) {
                if ($callbackIsCallable) {
                    $decl->value = preg_replace_callback(
                        $pattern,
                        function (array $matches) use ($callback) {
                            return call_user_func_array($callback, $matches);
                        },
                        $decl->value
                    );
                } else {
                    $decl->value = preg_replace($pattern, $callback, $decl->value);
                }
            } else {
                if ($callbackIsCallable) {
                    $decl->value = call_user_func($callback, $decl->value);
                } else {
                    $decl->value = str_replace($pattern, $callback, $decl->value);
                }
            }
        });

        return $this;
    }

    /**
     * Returns `true` if callback returns `true` for all of the container's children.
     *
     * @param callable $condition Iterator returns true or false
     *
     * @return bool is every child pass condition
     *
     * @example
     * $noPrefixes = $rule->every(function ($i) {
     *     return $i->prop[0] !== '-';
     *  });
     */
    public function every(callable $condition)
    {
        $result = true;
        foreach ($this->nodes as $node) {
            if (!$condition($node)) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * Returns `true` if callback returns `true` for (at least) one of the container's children.
     *
     * @param callable $condition Iterator returns true or false
     *
     * @return bool is some child pass condition
     *
     * @example
     * $hasPrefix = $rule->some(function ($i) {
     *     return $i->prop[0] === '-';
     * });
     */
    public function some(callable $condition)
    {
        $result = false;
        foreach ($this->nodes as $node) {
            if ($condition($node)) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Returns a $child's index within the Container->nodes array.
     *
     * @param Node|int $child Child of the current container
     *
     * @return int Child index (-1 if not found)
     *
     ** @example
     * $rule->index($rule->nodes[2]) //=> 2
     */
    public function index($child)
    {
        $result = -1;
        if (!empty($this->nodes)) {
            if (is_object($child)) {
                $i = array_search($child, $this->nodes, true);
                if ($i !== false) {
                    $result = $i;
                }
            } elseif (is_int($child) || (is_string($child) && is_numeric($child)) || is_float($child)) {
                $child = (int) $child;
                if ($child >= 0 && $child <= count($this->nodes)) {
                    $result = $child;
                }
            }
        }

        return $result;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'first':
                return empty($this->nodes) ? null : $this->nodes[0];
            case 'last':
                return empty($this->nodes) ? null : $this->nodes[count($this->nodes) - 1];
            default:
                return parent::__get($name);
        }
    }

    /**
     * @param Node[] $nodes
     *
     * @return Node[]
     */
    protected static function cleanSource(array $nodes)
    {
        $result = [];
        foreach ($nodes as $i) {
            if (isset($i->nodes) && !empty($i->nodes)) {
                $i->nodes = static::cleanSource($i->nodes);
            }
            if (isset($i->source)) {
                $i->source = null;
            }
            $result[] = $i;
        }

        return $result;
    }

    /**
     * @param mixed $nodes
     * @param mixed $sample
     * @param mixed $unused
     *
     * @throws Exception\CssSyntaxError
     *
     * @return Node[]
     */
    protected function normalize($nodes, $sample = null, $unused = null)
    {
        if (is_string($nodes)) {
            $nodes = static::cleanSource(Parser::parse($nodes)->nodes);
        } elseif (!is_array($nodes) || !ListUtil::isPlainArray($nodes)) {
            if (is_array($nodes)) {
                $nodes = (object) $nodes;
            }
            if (isset($nodes->type)) {
                if ($nodes->type === 'root') {
                    $nodes = $nodes->nodes;
                } else {
                    $nodes = [$nodes];
                }
            } elseif (isset($nodes->prop)) {
                if (!isset($nodes->value)) {
                    throw new Exception\CssSyntaxError('Value field is missed in node creation');
                } elseif (!is_string($nodes->value)) {
                    $nodes->value = (string) $nodes->value;
                }
                $nodes = [new Declaration((array) $nodes)];
            } elseif (isset($nodes->selector)) {
                $nodes = [new Rule((array) $nodes)];
            } elseif (isset($nodes->name)) {
                $nodes = [new AtRule((array) $nodes)];
            } elseif (isset($nodes->text)) {
                $nodes = [new Comment((array) $nodes)];
            } else {
                throw new Exception\CssSyntaxError('Unknown node type in node creation');
            }
        }
        $processed = [];
        foreach ($nodes as $i) {
            if (!isset($i->raws)) {
                $i = $this->rebuild($i);
            }
            if (isset($i->parent)) {
                $i = clone $i;
            }
            if ($i->raws->before === null) {
                if ($sample !== null) {
                    $before = $sample->raws->before;
                    if ($before !== null) {
                        $i->raws->before = preg_replace('/[^\s]/', '', $before);
                    }
                }
            }
            $i->parent = $this;

            $processed[] = $i;
        }

        return $processed;
    }

    /**
     * @param mixed $node
     * @param Node|null $parent
     *
     * @return Node
     */
    private function rebuild(Node $node, $parent = null)
    {
        if ($node->type === 'root') {
            $fix = new Root();
        } elseif ($node->type === 'atrule') {
            $fix = new AtRule();
        } elseif ($node->type === 'rule') {
            $fix = new Rule();
        } elseif ($node->type === 'decl') {
            $fix = new Declaration();
        } elseif ($node->type === 'comment') {
            $fix = new Comment();
        }
        $me = $this;
        foreach (get_object_vars($node) as $i => $value) {
            if ($i === 'nodes') {
                $fix->nodes = $node->nodes->map(function ($j) use ($me, $fix) {
                    $me->rebuild($j, $fix);
                });
            } elseif ($i === 'parent' && $parent) {
                $fix->parent = $parent;
            } else {
                $fix->$i = $value;
            }
        }

        return $fix;
    }
}
