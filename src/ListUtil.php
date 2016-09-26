<?php

namespace PostCSS;

/**
 * Contains helpers for safely splitting lists of CSS values, preserving parentheses and quotes.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/list.es6
 */
class ListUtil
{
    public static function split($string, array $separators, $last = false)
    {
        $array = [];
        $current = '';
        $split = false;

        $func = 0;
        $quote = false;
        $escape = false;

        $stringLength = strlen($string);

        for ($i = 0; $i < $stringLength; ++$i) {
            $letter = $string[$i];
            if ($quote) {
                if ($escape) {
                    $escape = false;
                } elseif ($letter === '\\') {
                    $escape = true;
                } elseif ($letter === $quote) {
                    $quote = false;
                }
            } elseif ($letter === '"' || $letter === '\'') {
                $quote = $letter;
            } elseif ($letter === '(') {
                $func += 1;
            } elseif ($letter === ')') {
                if ($func > 0) {
                    $func -= 1;
                }
            } elseif ($func === 0) {
                if (in_array($letter, $separators, true)) {
                    $split = true;
                }
            }
            if ($split) {
                if ($current !== '') {
                    $array[] = trim($current);
                }
                $current = '';
                $split = false;
            } else {
                $current .= $letter;
            }
        }

        if ($last || $current !== '') {
            $array[] = trim($current);
        }

        return $array;
    }

    /**
     * Safely splits space-separated values (such as those for `background`, `border-radius`, and other shorthand properties).
     *
     * @param string $string Space-separated values
     *
     * @return string[] Split values
     *
     * @example
     *
     * \PostCSS\ListUtil::space('1px calc(10% + 1px)') //=> ['1px', 'calc(10% + 1px)']
     */
    public static function space($string)
    {
        $spaces = [' ', '\n', '\t'];

        return static::split($string, $spaces);
    }

    /**
     * Safely splits comma-separated values (such as those for `transition-*` and `background` properties).
     *
     * @param string $string Comma-separated values
     *
     * @return string[] Split values
     *
     * @example
     * \PostCSS\ListUtil::comma('black, linear-gradient(white, black)') //=> ['black', 'linear-gradient(white, black)']
     */
    public static function comma($string)
    {
        $comma = ',';

        return static::split($string, [$comma], true);
    }

    /**
     * Check if a variable is an array whose keys are numeric.
     *
     * @param mixed $v
     * @param bool $ifEmptyArrayReturn What to return if it's an empty array
     *
     * @return bool
     */
    public static function isPlainArray($v, $ifEmptyArrayReturn = false)
    {
        $result = false;
        if (is_array($v)) {
            $keys = array_keys($v);
            if (empty($keys)) {
                $result = $ifEmptyReturn;
            } else {
                $result = true;
                foreach ($keys as $key) {
                    if (!is_int($key)) {
                        $result = false;
                        break;
                    }
                }
            }
        }

        return $result;
    }
}
