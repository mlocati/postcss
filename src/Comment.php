<?php

namespace PostCSS;

/**
 * Represents a comment between declarations or statements (rule and at-rules).
 *
 * Comments inside selectors, at-rule parameters, or declaration values will be stored in the `raws` properties explained above.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/comment.es6
 */
class Comment extends Node
{
    /**
     * The comment’s text.
     *
     * @var string|null
     */
    public $text = null;

    public function __construct(array $defaults = [])
    {
        parent::__construct($defaults);
        $this->type = 'comment';
    }

    /**
     * @deprecated Comment->left was deprecated. Use Comment->raws->left
     * @deprecated Comment->right was deprecated. Use Comment->raws->right
     */
    public function __get($name)
    {
        switch ($name) {
            case 'left':
                return $this->raws->left;
            case 'right':
                return $this->raws->right;
            default:
                return parent::__get($name);
        }
    }

    /**
     * @deprecated Comment->left was deprecated. Use Comment->raws->left
     * @deprecated Comment->right was deprecated. Use Comment->raws->right
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'left':
                $this->raws->left = $value;
                break;
            case 'right':
                $this->raws->right = $value;
                break;
            default:
                return parent::__set($name);
        }
    }
}
