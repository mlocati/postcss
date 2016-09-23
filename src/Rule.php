<?php

namespace PostCSS;

/**
 * Represents a CSS rule: a selector followed by a declaration block.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/rule.es6
 */
class Rule extends Container
{
    /**
     * @var string|null
     */
    public $selector = null;

    public function __construct(array $defaults = [])
    {
        $selectors = null;
        if (isset($defaults['selectors'])) {
            $selectors = $defaults['selectors'];
            unset($defaults['selectors']);
        }
        parent::__construct($defaults);
        $this->type = 'rule';
        if ($this->nodes === null) {
            $this->nodes = [];
        }
        if ($selectors !== null) {
            $this->setSelectors($selectors);
        }
    }

    /**
     * An array containing the rule's individual selectors.
     * Groups of selectors are split at commas.
     */
    public function getSelectors()
    {
        return ListUtil::comma($this->selector);
    }

    public function setSelectors(array $values)
    {
        if (!isset($this->selector) || !$this->selector || !preg_match('/,\s*/', $this->selector, $match)) {
            $match = null;
        }
        $sep = ($match !== null) ? $match[0] : ','.$this->raw('between', 'beforeOpen');
        $this->selector = implode($sep, $values);
    }

    /**
     * @deprecated Rule#_selector is deprecated. Use Rule#raws.selector
     */
    public function _getSelector()
    {
        return $this->raws->selector;
    }

    /**
     * @deprecated Rule#_selector is deprecated. Use Rule#raws.selector
     */
    public function _setSelector($val)
    {
        $this->raws->selector = $val;
    }
}
