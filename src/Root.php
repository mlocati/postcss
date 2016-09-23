<?php

namespace PostCSS;

/**
 * Represents a CSS file and contains all its parsed nodes.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/root.es6
 */
class Root extends Container
{
    public function __construct(array $defaults = [])
    {
        parent::__construct($defaults);
        $this->type = 'root';
        if ($this->nodes === null) {
            $this->nodes = [];
        }
    }

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
     * Returns a {@link Result} instance representing the root's CSS.
     *
     * @param ProcessOptions $opts Options with only `to` and `map` keys
     *
     * @return Result Result with current root's CSS
     */
    public function toResult(array $opts = [])
    {
        $lazy = new LazyResult(new Processor(), $this, $opts);

        return $lazy->stringify();
    }

    /**
     * @deprecated Use Root#removeChild
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
     * @deprecated Use Root#source->input->map
     */
    public function prevMap()
    {
        return $this->source['input']->map;
    }
}
