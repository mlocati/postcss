<?php

namespace PostCSS\Tests;

use PostCSS\Input;
use PostCSS\Parser;
use PostCSS\Exception\CssSyntaxError;

class TokenizeTest extends \PHPUnit_Framework_TestCase
{
    private function runTokenizer($css, array $opts, array $tokens = null)
    {
        if ($tokens === null) {
            $tokens = $opts;
            $opts = [];
        }
        $this->assertSame($tokens, Parser::getTokens(new Input($css, $opts)));
    }

    private function ignoreRunTokenizer($css, array $tokens)
    {
        $this->assertSame($tokens, Parser::getTokens(new Input($css), ['ignoreErrors' => true]));
    }

    public function testTokenizesEmptyFile()
    {
        $this->runTokenizer(
            '',
            [
            ]
        );
    }

    public function testTokenizesSpace()
    {
        $this->runTokenizer(
            "\r\n \f\t",
            [
                ['space', "\r\n \f\t"],
            ]
        );
    }

    public function testTokenizesWord()
    {
        $this->runTokenizer(
            'ab',
            [
                ['word', 'ab', 1, 1, 1, 2],
            ]
        );
    }

    public function testSplitsWordByExclamationMark()
    {
        $this->runTokenizer(
            'aa!bb',
            [
                ['word', 'aa',  1, 1, 1, 2],
                ['word', '!bb', 1, 3, 1, 5],
            ]
        );
    }

    public function testChangesLinesInSpaces()
    {
        $this->runTokenizer(
            "a \n b",
            [
                ['word',  'a', 1, 1, 1, 1],
                ['space', " \n "],
                ['word',  'b', 2, 2, 2, 2],
            ]
        );
    }

    public function testTokenizesControlChars()
    {
        $this->runTokenizer(
            '{:;}',
            [
                ['{', '{', 1, 1],
                [':', ':', 1, 2],
                [';', ';', 1, 3],
                ['}', '}', 1, 4],
            ]
        );
    }

    public function testEscapesControlSymbols()
    {
        $this->runTokenizer(
            '\\(\\{\\"\\@\\\\""',
            [
                ['word',   '\\(',  1,  1, 1,  2],
                ['word',   '\\{',  1,  3, 1,  4],
                ['word',   '\\"',  1,  5, 1,  6],
                ['word',   '\\@',  1,  7, 1,  8],
                ['word',   '\\\\', 1,  9, 1, 10],
                ['string', '""',   1, 11, 1, 12],
            ]
        );
    }

    public function testEscapesBackslash()
    {
        $this->runTokenizer(
            '\\\\\\\\{',
            [
                ['word', '\\\\\\\\', 1, 1, 1, 4],
                ['{',    '{',        1, 5],
            ]
        );
    }

    public function testTokenizesSimpleBrackets()
    {
        $this->runTokenizer(
            '(ab)',
            [
                ['brackets', '(ab)', 1, 1, 1, 4],
            ]
        );
    }

    public function testTokenizesSquareBrackets()
    {
        $this->runTokenizer(
            'a[bc]',
            [
                ['word', 'a',  1, 1, 1, 1],
                ['[',    '[',  1, 2],
                ['word', 'bc', 1, 3, 1, 4],
                [']',    ']',  1, 5],
            ]
        );
    }

    public function testTokenizesComplicatedBrackets()
    {
        $this->runTokenizer(
            "(())(\"\")(/**/)(\\\\)(\n)(",
            [
                ['(',        '(',    1, 1],
                ['brackets', '()',   1, 2, 1, 3],
                [')',        ')',    1, 4],
                ['(',        '(',    1, 5],
                ['string',   '""',   1, 6, 1, 7],
                [')',        ')',    1, 8],
                ['(',        '(',    1, 9],
                ['comment',  '/**/', 1, 10, 1, 13],
                [')',        ')',    1, 14],
                ['(',        '(',    1, 15],
                ['word',     '\\\\', 1, 16, 1, 17],
                [')',        ')',    1, 18],
                ['(',        '(',    1, 19],
                ['space',    "\n"],
                [')',        ')',    2, 1],
                ['(',        '(',    2, 2],
            ]
        );
    }

    public function testTokenizesString()
    {
        $this->runTokenizer(
            '\'"\'"\\""',
            [
                ['string', '\'"\'',  1, 1, 1, 3],
                ['string', '"\\""', 1, 4, 1, 7],
            ]
        );
    }

    public function testTokenizesEscapedString()
    {
        $this->runTokenizer(
            '"\\\\"',
            [
                ['string', '"\\\\"', 1, 1, 1, 4],
            ]
        );
    }

    public function testChangesLinesInStrings()
    {
        $this->runTokenizer(
            "\"\n\n\"\"\n\n\"",
            [
                ['string', "\"\n\n\"", 1, 1, 3, 1],
                ['string', "\"\n\n\"", 3, 2, 5, 1],
            ]
        );
    }

    public function testTokenizesAtWord()
    {
        $this->runTokenizer(
            '@word ', [['at-word', '@word', 1, 1, 1, 5], ['space', ' '],
            ]
        );
    }

    public function testTokenizesAtWordEnd()
    {
        $this->runTokenizer(
            '@one{@two()@three""@four;',
            [
        ['at-word',  '@one',   1,  1, 1,  4],
        ['{',        '{',      1,  5],
        ['at-word',  '@two',   1,  6, 1,  9],
        ['brackets', '()',     1, 10, 1, 11],
        ['at-word',  '@three', 1, 12, 1, 17],
        ['string',   '""',     1, 18, 1, 19],
        ['at-word',  '@four',  1, 20, 1, 24],
        [';',        ';',      1, 25],
        ]
        );
    }

    public function testTokenizesUrls()
    {
        $this->runTokenizer(
            'url(/*\\))',
            [
                ['word',     'url',     1, 1, 1, 3],
                ['brackets', '(/*\\))', 1, 4, 1, 9],
            ]
        );
    }

    public function testTokenizesQuotedUrls()
    {
        $this->runTokenizer(
            'url(")")',
            [
                ['word',   'url', 1, 1, 1, 3],
                ['(',      '(',   1, 4],
                ['string', '")"', 1, 5, 1, 7],
                [')',      ')',   1, 8],
            ]
        );
    }

    public function testTokenizesAtSymbol()
    {
        $this->runTokenizer(
            '@',
            [
                ['at-word', '@', 1, 1, 1, 1],
            ]
        );
    }

    public function testTokenizesComment()
    {
        $this->runTokenizer(
            "/* a\nb */",
            [
                ['comment', "/* a\nb */", 1, 1, 2, 4],
            ]
        );
    }

    public function testChangesLinesInComments()
    {
        $this->runTokenizer(
            "a/* \n */b",
            [
                ['word',    'a',        1, 1, 1, 1],
                ['comment', "/* \n */", 1, 2, 2, 3],
                ['word',    'b',        2, 4, 2, 4],
            ]
        );
    }

    public function testSupportsLineFeed()
    {
        $this->runTokenizer(
            "a\fb",
            [
                ['word',  'a', 1, 1, 1, 1],
                ['space', "\f"],
                ['word',  'b', 2, 1, 2, 1],
            ]
        );
    }

    public function testSupportsCarriageReturn()
    {
        $this->runTokenizer(
            "a\rb\r\nc",
            [
                ['word',  'a', 1, 1, 1, 1],
                ['space', "\r"],
                ['word',  'b', 2, 1, 2, 1],
                ['space', "\r\n"],
                ['word',  'c', 3, 1, 3, 1],
            ]
        );
    }

    public function testTokenizesCSS()
    {
        $css = "a {\n  content: \"a\";\n  width: calc(1px;)\n  }\n/* small screen */\n@media screen {}";
        $this->runTokenizer(
            $css,
            [
                ['word',     'a',                  1,  1, 1,  1],
                ['space',    ' '],
                ['{',        '{',                  1,  3],
                ['space',    "\n  "],
                ['word',     'content',            2,  3, 2,  9],
                [':',        ':',                  2, 10],
                ['space',    ' '],
                ['string',   '"a"',                2, 12, 2, 14],
                [';',        ';',                  2, 15],
                ['space',    "\n  "],
                ['word',     'width',              3,  3, 3,  7],
                [':',        ':',                  3,  8],
                ['space',    ' '],
                ['word',     'calc',               3, 10, 3, 13],
                ['brackets', '(1px;)',             3, 14, 3, 19],
                ['space',    "\n  "],
                ['}',        '}',                  4,  3],
                ['space',    "\n"],
                ['comment',  '/* small screen */', 5,  1, 5, 18],
                ['space',    "\n"],
                ['at-word',  '@media',             6,  1, 6,  6],
                ['space',    ' '],
                ['word',     'screen',             6,  8, 6, 13],
                ['space',    ' '],
                ['{',        '{',                  6, 15],
                ['}',        '}',                  6, 16],
            ]
        );
    }

    public function testThrowsErrorOnUnclosedString()
    {
        $err = null;
        try {
            Parser::getTokens(new Input(' "'));
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $x);
        $this->assertRegExp('/:1:2: Unclosed quote/', $x->getMessage());
    }

    public function testThrowsErrorOnUnclosedComment()
    {
        $err = null;
        try {
            Parser::getTokens(new Input(' /*'));
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $x);
        $this->assertRegExp('/:1:2: Unclosed comment/', $x->getMessage());
    }

    public function testThrowsErrorOnUnclosedUrl()
    {
        $err = null;
        try {
            Parser::getTokens(new Input('url('));
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $x);
        $this->assertRegExp('/:1:4: Unclosed bracket/', $x->getMessage());
    }

    public function testIgnoresUnclosingStringOnRequest()
    {
        $this->ignoreRunTokenizer(
            ' "',
            [
                ['space', ' '],
                ['string', '"', 1, 2, 1, 3],
            ]
        );
    }

    public function testIgnoresUnclosingCommentOnRequest1()
    {
        $this->ignoreRunTokenizer(
            ' /*',
            [
                ['space', ' '],
                ['comment', '/*', 1, 2, 1, 4],
            ]
        );
    }

    public function testIgnoresUnclosingCommentOnRequest2()
    {
        $this->ignoreRunTokenizer(
            'url(',
            [
                ['word',     'url', 1, 1, 1, 3],
                ['brackets', '(',   1, 4, 1, 4],
            ]
        );
    }
}
