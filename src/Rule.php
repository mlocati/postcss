<?php

namespace PostCSS;

/**
 * Represents a CSS rule: a selector followed by a declaration block.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/rule.es6
 *
 * @example
 * $root = \PostCSS\Parser::parse('a{}');
 * $rule = $root->first;
 * $rule->type    //=> 'rule'
 * (string) $rule //=> 'a{}'
 *
 * @property string[] $selectors An array containing the rule's individual selectors. Groups of selectors are split at commas
 * @property @deprecated string $_selector Rule->_selector is deprecated. Use Rule->raws->selector
 */
class Rule extends Container
{
    /**
     * The rule’s full selector represented as a string.
     *
     * @var string|null
     */
    public $selector = null;

    /**
     * @param array $defaults
     */
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
            $this->selectors = $selectors;
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'selectors':
                return ListUtil::comma($this->selector);
            case '_selector':
                return $this->raws->selector;
            default:
                return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'selectors':
                if (!$this->selector || !preg_match('/,\s*/', $this->selector, $match)) {
                    $match = null;
                }
                $sep = ($match !== null) ? $match[0] : ','.$this->raw('between', 'beforeOpen');
                $this->selector = implode($sep, (array) $value);
                break;
            case '_selector':
                $this->raws->selector = $value;
                break;
            default:
                parent::__set($name, $value);
                break;
        }
    }
}
