<?php

namespace PostCSS;

/**
 * Contains helpers for working with vendor prefixes.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/vendor.es6
 */
class Vendor
{
    /**
     * Returns the vendor prefix extracted from an input string.
     *
     * @param string $prop String with or without vendor prefix
     *
     * @return string Vendor prefix or empty string
     *
     * @example
     * \PostCSS\Vendor::prefix('-moz-tab-size') //=> '-moz-'
     * \PostCSS\Vendor::prefix('tab-size')      //=> ''
     */
    public static function prefix($prop)
    {
        $result = '';
        $prop = (string) $prop;
        if (isset($prop[1]) && $prop[0] === '-') {
            $sep = strpos($prop, '-', 1);
            if ($sep !== false) {
                $result = substr($prop, 0, $sep + 1);
            }
        }

        return $result;
    }

    /**
     * Returns the input string stripped of its vendor prefix.
     *
     * @param string $prop String with or without vendor prefix
     *
     * @return string String name without vendor prefixes
     *
     * @example
     * \PostCSS\Vendor::unprefixed('-moz-tab-size') //=> 'tab-size'
     */
    public static function unprefixed($prop)
    {
        $prop = (string) $prop;
        $result = $prop;
        if (isset($prop[1]) && $prop[0] === '-') {
            $sep = strpos($prop, '-', 1);
            if ($sep !== false) {
                $result = substr($prop, $sep + 1);
            }
        }

        return $result;
    }
}
