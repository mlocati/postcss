<?php

namespace PostCSS;

/**
 * Represents a CSS declaration.
 *
 * @example
 * $root = \PostCSS\Parser::parse('a { color: black }');
 * $decl = $root->first->first;
 * $decl->type     //=> 'decl'
 * (string) $decl  //=> ' color: black'
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/declaration.es6
 */
class Declaration extends Node
{
    public $important = null;

    public $prop = null;

    public $value = null;

    public function __construct(array $defaults = [])
    {
        parent::__construct($defaults);
        $this->type = 'decl';
    }

    /**
     * @deprecated Declaration->_value was deprecated. Use Declaration->raws->value
     * @deprecated Declaration->_important was deprecated. Use Declaration->raws->important
     */
    public function __get($name)
    {
        switch ($name) {
            case '_value':
                return $this->raws->value;
            case '_important':
                return $this->raws->important;
            default:
                return parent::__get($name);
        }
    }

    /**
     * @deprecated Declaration->_value was deprecated. Use Declaration->raws->value
     * @deprecated Declaration->_important was deprecated. Use Declaration->raws->important
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case '_value':
                $this->raws->value = $value;
                break;
            case '_important':
                $this->raws->important = $value;
                break;
            default:
                return parent::__set($name);
        }
    }
}
