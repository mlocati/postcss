<?php

namespace PostCSS;

/**
 * Represents a CSS file and contains all its parsed nodes.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/root.es6
 *
 * @example
 * $root = \PostCSS\Parser::parse('a{color:black} b{z-index:2}');
 * $root->type         //=> 'root'
 * count($root->nodes) //=> 2
 */
class Root extends Container
{
    /**
     * @param array $defaults
     */
    public function __construct(array $defaults = [])
    {
        parent::__construct($defaults);
        $this->type = 'root';
        if ($this->nodes === null) {
            $this->nodes = [];
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see Container::removeChild()
     */
    public function removeChild($child)
    {
        $child = $this->index($child);

        if ($child === 0 && count($this->nodes) > 1) {
            $before = $this->nodes[$child]->raws->before;
            if ($before !== null) {
                $this->nodes[1]->raws->before = $before;
            } else {
                unset($this->nodes[1]->raws->before);
            }
        }

        return parent::removeChild($child);
    }

    /**
     * {@inheritdoc}
     *
     * @see Container::normalize()
     */
    public function normalize($child, $sample = null, $type = null)
    {
        $nodes = parent::normalize($child);
        if ($sample) {
            if ($type === 'prepend') {
                if (count($this->nodes) > 1) {
                    $before = $this->nodes[1]->raws->before;
                    if ($before !== null) {
                        $sample->raws->before = $before;
                    } else {
                        unset($sample->raws->before);
                    }
                } else {
                    unset($sample->raws->before);
                }
            } elseif ($this->first !== $sample) {
                foreach ($nodes as $node) {
                    $before = $sample->raws->before;
                    if ($before !== null) {
                        $node->raws->before = $before;
                    } else {
                        unset($sample->raws->before);
                    }
                }
            }
        }

        return $nodes;
    }

    /**
     * Returns a Result instance representing the root's CSS.
     *
     * @param array $opts Options with only `to` and `map` keys. Same values as LazyResult::__constructor
     *
     * @return Result Result with current root's CSS
     */
    public function toResult(array $opts = [])
    {
        $lazy = new LazyResult(new Processor(), $this, $opts);

        return $lazy->stringify();
    }

    /**
     * @deprecated Use Root->removeChild
     */
    public function remove()
    {
        $args = func_get_args();
        if (count($args) !== 1) {
            throw new \Exception(__METHOD__.' expects 1 parameter ($child)');
        }
        $child = $args[0];
        $this->removeChild($child);
    }

    /**
     * @deprecated Use Root->source->input->map
     */
    public function prevMap()
    {
        return $this->source['input']->map;
    }
}
