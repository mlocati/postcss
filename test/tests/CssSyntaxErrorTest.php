<?php

namespace PostCSS\Tests;

use PostCSS\Exception\CssSyntaxError;
use PostCSS\Parser;
use PostCSS\Terminal;
use PostCSS\Declaration;
use PostCSS\SourceMap\Concat;
use PostCSS\Path\NodeJS;
use PostCSS\Processor;
use PostCSS\Plugin\ClosurePlugin;
use React\Promise\Promise;

class CssSyntaxErrorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $css
     * @param array  $opts
     *
     * @return CssSyntaxError|null
     */
    protected static function parseError($css, array $opts = [])
    {
        $error = null;
        try {
            Parser::parse($css, $opts);
        } catch (CssSyntaxError $e) {
            $error = $e;
        }

        return $error;
    }

    public function testSavesSource()
    {
        $error = self::parseError("a {\n  content: \"\n}");

        $this->assertInstanceOf(CssSyntaxError::class, $error);
        $this->assertSame('<css input>:2:12: Unclosed quote', $error->getMessage());
        $this->assertSame('Unclosed quote', $error->getPostCSSReason());
        $this->assertSame(2, $error->getPostCSSLine());
        $this->assertSame(12, $error->getPostCSSColumn());
        $this->assertSame("a {\n  content: \"\n}", $error->getPostCSSSource());

        $this->assertSame([
            'line' => $error->getPostCSSLine(),
            'column' => $error->getPostCSSColumn(),
            'source' => $error->getPostCSSSource(),
        ], $error->input);
    }

    public function testHasStackTrace()
    {
        $error = self::parseError("a {\n  content: \"\n}");
        $this->assertRegExp('/'.preg_quote(basename(__FILE__), '/').'/', $error->getTraceAsString());
    }

    public function testHighlightsBrokenLineWithColors()
    {
        $this->assertSame(
            '> 1 | a '.Terminal::green('{')."\n    | ^",
            self::parseError('a {')->showSourceCode(true)
        );
    }

    public function testHighlightsMultilineTokensWithColorsButNotTheLineGutter()
    {
        $this->assertSame(
            '> 1 | '.Terminal::red('"a')."\n    | ^\n  2 | ".Terminal::red('b"'),
            self::parseError("\"a\nb\"")->showSourceCode(true)
        );
        $this->assertSame(
            '  1 | '.Terminal::grey('/*')."\n> 2 | ".Terminal::grey('*/')."a\n    |   ^",
            self::parseError("/*\r\n*/a")->showSourceCode(true)
        );
    }

    public function testHighlightsBrokenLine()
    {
        $this->assertSame(
            "  1 | a {\n> 2 |   content: \"\n    |            ^\n  3 | }",
            self::parseError("a {\n  content: \"\n}")->showSourceCode(false)
        );
    }

    public function testHighlightsBrokenLineWhenIndentedWithTabs()
    {
        $this->assertSame(
            "  1 | a {\n> 2 | \t \t  content:\t\"\n    | \t \t          \t^\n  3 | }",
            self::parseError("a {\n\t \t  content:\t\"\n}")->showSourceCode(false)
        );
    }

    public function testHighlightsSmallCodeExample()
    {
        $this->assertSame(
            "> 1 | a {\n    | ^",
            self::parseError('a {')->showSourceCode(false)
        );
    }

    public function testAddLeadingSpaceForLineNumbers()
    {
        $css = "\n\n\n\n\n\n\na {\n  content: \"\n}\n\n\n";
        $this->assertSame(
            "   7 | \n   8 | a {\n>  9 |   content: \"\n     |            ^\n  10 | }\n  11 | ",
            self::parseError($css)->showSourceCode(false)
        );
    }

    public function testPrintsWithHighlight()
    {
        $this->assertSame(
            "CssSyntaxError: <css input>:1:1: Unclosed block\n\n> 1 | a {\n    | ^\n",
            Terminal::stripAnsi((string) self::parseError('a {'))
        );
    }

    public function testMissesHighlightsWithoutSourceContent()
    {
        $error = self::parseError('a {');
        $error->setPostCSSSource(null);
        $this->assertSame(
            'CssSyntaxError: <css input>:1:1: Unclosed block',
            (string) $error
        );
    }

    public function testMissesPositionWithoutSource()
    {
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);
        $error = $decl->error('Test');
        $this->assertSame(
            'CssSyntaxError: <css input>: Test',
            (string) $error
        );
    }

    public function testUsesSourceMap()
    {
        $concat = new Concat(true, 'all.css');
        $concat->add('a.css', "a { }\n");
        $concat->add('b.css', "\nb {\n");

        $error = self::parseError(
            $concat->getContent(),
            [
                'from' => 'build/all.css',
                'map' => ['prev' => $concat->getSourceMapAsString()],
            ]
        );

        $this->assertSame(
            NodeJS::resolve('b.css'),
            $error->getPostCSSFile()
        );
        $this->assertSame(
            2,
            $error->getPostCSSLine()
        );
        $this->assertSame(
            '',
            $error->getPostCSSSource()
        );
        $expected = [
            'file' => NodeJS::resolve('build/all.css'),
            'line' => 3,
            'column' => 1,
            'source' => "a { }\n\nb {\n",
        ];
        $received = $error->input;
        ksort($expected);
        ksort($received);
        $this->assertSame(
            $expected,
            $received
        );
    }

    public function testShowsOriginSource()
    {
        $input = (new Processor())->process(
            'a{}',
            [
                'from' => '/a.css',
                'to' => '/b.css',
                'map' => ['inline' => false],
            ]
        );
        $error = self::parseError(
            'a{',
            [
                'from' => '/b.css',
                'to' => '/c.css',
                'map' => ['prev' => $input->map],
            ]
        );
        $this->assertSame('a{}', $error->getPostCSSSource());
    }

    public function testDoesNotUsesWrongSourceMap()
    {
        $error = self::parseError(
            "a { }\nb {",
            [
                'from' => 'build/all.css',
                'map' => [
                    'prev' => [
                        'version' => 3,
                        'file' => 'build/all.css',
                        'sources' => ['a.css', 'b.css'],
                        'mappings' => 'A',
                    ],
                ],
            ]
        );
        $this->assertSame(NodeJS::resolve('build/all.css'), $error->getPostCSSFile());
    }

    public function testSetSourcePlugin()
    {
        $error = Parser::parse('a{}')->first->error('Error', ['plugin' => 'PL']);
        $this->assertSame('PL', $error->getPostCSSPlugin());
        $this->assertRegExp('/^CssSyntaxError: PL: <css input>:1:1: Error/', (string) $error);
    }

    public function testSetSourcePluginAutomatically()
    {
        $plugin = new ClosurePlugin(
            function ($css) {
                throw $css->first->error('Error');
            },
            'test-plugin'
        );

        $me = $this;
        (new Processor([$plugin]))->process('a{}')->catchError(function ($error) use ($me, $plugin) {
            if (!($error instanceof CssSyntaxError)) {
                throw $error;
            }
            $me->assertSame($plugin, $error->getPostCSSPlugin());
            $me->assertRegExp('/test-plugin/', (string) $error);
        });
    }

    public function testSetPluginAutomaticallyInAsync()
    {
        $plugin = new ClosurePlugin(
            function ($css) {
                return new Promise(function ($resolve, $reject) use ($css) {
                    $reject($css->first->error('Error'));
                });
            },
            'async-plugin'
        );
        $me = $this;
        (new Processor([$plugin]))->process('a{}')->catchError(function ($error) use ($me, $plugin) {
            if (!($error instanceof CssSyntaxError)) {
                throw $error;
            }
            $me->assertSame($plugin, $error->getPostCSSPlugin());
        });
    }
}
