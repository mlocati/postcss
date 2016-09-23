<?php

namespace PostCSS;

/**
 * Represents an at-rule.
 *
 * If it’s followed in the CSS by a {} block, this node will have
 * a nodes property representing its children.
 *
 * @example
 * $root = \PostCSS\Parser::parse('@charset "UTF-8"; @media print {}');
 * $charset = $root->first;
 * $charset->type  //=> 'atrule' / instanceof \PostCSS\AtRule
 * $charset->nodes //=> not set
 * $media = $root->last
 * $media->nodes   //=> []
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/at-rule.es6
 */
class AtRule extends Container
{
    /**
     * The at-rule’s name immediately follows the `@`.
     *
     * @example
     * $root  = \PostCSS\Parser::parse('@media print {}');
     * $media = $root->first;
     * $media->name //=> 'media'
     *
     * @var string|null
     */
    public $name = null;

    /**
     * The at-rule’s parameters, the values that follow the at-rule’s name but precede any {} block.
     *
     * @example
     * $root  = \PostCSS\Parser::parse('@media print, screen {}');
     * $media = $root->first;
     * $media->params //=> 'print, screen'
     *
     * @var string|null
     */
    public $params = null;

    public function __construct(array $defaults = [])
    {
        parent::__construct($defaults);
        $this->type = 'atrule';
    }

    public function append($children/*, ...*/)
    {
        if ($this->nodes === null) {
            $this->nodes = [];
        }
        call_user_func_array('parent::append', func_get_args());
    }

    public function prepend($children/*, ...*/)
    {
        if ($this->nodes === null) {
            $this->nodes = [];
        }
        call_user_func_array('parent::prepend', func_get_args());
    }

    /**
     * @deprecated AtRule->afterName was deprecated. Use AtRule->raws->afterName
     * @deprecated AtRule->_params was deprecated. Use AtRule->raws->params
     */
    public function __get($name)
    {
        switch ($name) {
            case 'afterName':
                return $this->raws->afterName;
            case '_params':
                return $this->raws->params;
            default:
                return parent::__get($name);
        }
    }

    /**
     * @deprecated AtRule->afterName was deprecated. Use AtRule->raws->afterName
     * @deprecated AtRule->_params was deprecated. Use AtRule->raws->params
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'afterName':
                $this->raws->afterName = $value;
                break;
            case '_params':
                $this->raws->params = $value;
                break;
            default:
                return parent::__set($name, $value);
        }
    }
}
