<?php

namespace PostCSS;

/**
 * @link https://github.com/postcss/postcss/blob/master/lib/terminal-highlight.es6
 */
class Terminal
{
    protected static function getHighlightTheme()
    {
        return [
            'brackets' => [36, 39], // cyan
            'string' => [31, 39], // red
            'at-word' => [31, 39], // red
            'comment' => [90, 39], // gray
            '{' => [32, 39], // green
            '}' => [32, 39], // green
            ':' => [1, 22], // bold
            ';' => [1, 22], // bold
            '(' => [1, 22], // bold
            ')' => [1, 22],  // bold
        ];
    }

    public static function code($color)
    {
        return "\x1b".$color.'m';
    }

    public static function highlight($css)
    {
        $highlightTheme = static::getHighlightTheme();
        $tokens = Parser::getTokens(new Input($css), ['ignoreErrors' => true]);
        $result = [];
        foreach ($tokens as $token) {
            if (isset($highlightTheme[$token[0]])) {
                $color = $highlightTheme[$token[0]];
                $chunks = preg_split('/\r?\n/', $token[1]);
                foreach ($chunks as $i => $chunk) {
                    if ($i > 0) {
                        $result[] = "\n";
                    }
                    $result[] = static::code($color[0]).$chunk.static::code($color[1]);
                }
            } else {
                $result[] = $token[1];
            }
        }

        return implode('', $result);
    }

    protected static function colorize($text, $before, $after)
    {
        return static::code($before).$text.static::code($after);
    }

    public static function black($text)
    {
        return static::colorize($text, 30, 39);
    }

    public static function red($text)
    {
        return static::colorize($text, 31, 39);
    }

    public static function green($text)
    {
        return static::colorize($text, 32, 39);
    }

    public static function yellow($text)
    {
        return static::colorize($text, 33, 39);
    }

    public static function blue($text)
    {
        return static::colorize($text, 34, 39);
    }

    public static function magenta($text)
    {
        return static::colorize($text, 35, 39);
    }

    public static function cyan($text)
    {
        return static::colorize($text, 36, 39);
    }

    public static function white($text)
    {
        return static::colorize($text, 37, 39);
    }

    public static function gray($text)
    {
        return static::colorize($text, 90, 39);
    }

    public static function grey($text)
    {
        return static::gray($text);
    }

    public static function stripAnsi($text)
    {
        $result = (string) $text;
        if ($result !== '') {
            $result = preg_replace('/[\x1b\x9b][[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-ORZcf-nqry=><]/', $result, '');
        }

        return $text;
    }
}
