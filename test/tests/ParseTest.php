<?php

namespace PostCSS\Tests;

use PostCSS\Parser;
use PostCSS\Node;
use PostCSS\Root;
use PostCSS\Path\NodeJS as Path;
use PostCSS\Exception\CssSyntaxError;

class ParseTest extends Helpers\CasesTest
{
    protected static function clean(array $node)
    {
        if (isset($node['source'])) {
            unset($node['source']['input']['css']);
            $node['source']['input']['file'] = basename($node['source']['input']['file']);
        }
        unset($node['indexes']);
        unset($node['lastEach']);
        unset($node['rawCache']);
        if (isset($node['nodes'])) {
            $node['nodes'] = array_map([self::class, 'clean'], $node['nodes']);
        }

        return $node;
    }

    protected static function jsonify(Node $node)
    {
        $cleaned = self::clean($node->toJSON());

        return json_encode($cleaned, JSON_PRETTY_PRINT);
    }

    protected static function sortArray($arr)
    {
        $wasString = is_string($arr);
        if ($wasString) {
            $arr = json_decode($arr, true);
        }
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = static::sortArray($v);
            }
        }

        return $wasString ? json_encode($arr, JSON_PRETTY_PRINT) : $arr;
    }

    public function testWorksWithFileReads()
    {
        $case = static::getParseCases('atrule-empty');
        $this->assertNotNull($case);
        $fd = @fopen($case['path']['css'], 'rb');
        $this->assertInternalType('resource', $fd);
        $this->assertInstanceOf(Root::class, Parser::parse($fd));
        @fclose($fd);
    }

    public function providerTestParses()
    {
        $r = [];
        foreach (static::getParseCases() as $id => $data) {
            if (isset($data['contents']['css']) && isset($data['contents']['json'])) {
                $r[] = [$id.'.css', $data['contents']['css'], $data['contents']['json']];
            }
        }

        return $r;
    }

    /**
     * @param string $name
     * @param string $css
     * @param string $json
     *
     * @dataProvider providerTestParses
     */
    public function testParses($name, $css, $json)
    {
        $parsed = static::jsonify(Parser::parse($css, ['from' => $name]));

        $this->assertSame(self::sortArray($json), self::sortArray($parsed), 'Parsing '.$name);
    }

    public function testSavesSourceFile()
    {
        $css = Parser::parse('a {}', ['from' => 'a.css']);
        $this->assertSame('a {}', $css->first->source['input']->css);
        $this->assertSame(Path::resolve('a.css'), $css->first->source['input']->file);
        $this->assertSame(Path::resolve('a.css'), $css->first->source['input']->from);
    }

    public function testKeepsAbsolutePathInSource()
    {
        $css = Parser::parse('a {}', ['from' => 'http://example.com/a.css']);
        $this->assertSame('http://example.com/a.css', $css->first->source['input']->file);
        $this->assertSame('http://example.com/a.css', $css->first->source['input']->from);
    }

    public function testSavesSourceFileOnPreviousMap()
    {
        $root1 = Parser::parse('a {}', ['map' => ['inline' => true]]);
        $css = $root1->toResult(['map' => ['inline' => true]])->css;
        $root2 = Parser::parse($css);
        $this->assertSame(Path::resolve('to.css'), $root2->first->source['input']->file);
    }

    public function testSetsUniqueIDForFileWithoutName()
    {
        $css1 = Parser::parse('a {}');
        $css2 = Parser::parse('a {}');
        $this->assertRegExp('/^<input css \d+>$/', $css1->first->source['input']->id);
        $this->assertRegExp('/^<input css \d+>$/', $css1->first->source['input']->from);
        $this->assertNotEquals($css1->first->source['input']->id, $css2->first->source['input']->id);
    }

    public function testSetsParentNode()
    {
        $file = static::getParseCases('atrule-rules')['path']['css'];
        $css = Parser::parse(file_get_contents($file));

        $support = $css->first;
        $keyframes = $support->first;
        $from = $keyframes->first;
        $decl = $from->first;

        $this->assertSame($from, $decl->parent);
        $this->assertSame($keyframes, $from->parent);
        $this->assertSame($css, $support->parent);
        $this->assertSame($support, $keyframes->parent);
    }

    public function testIgnoresWrongCloseBracket()
    {
        $root = Parser::parse('a { p: ()) }');
        $this->assertSame('())', $root->first->first->value);
    }
    public function testIgnoresSymbolsBeforeDeclaration()
    {
        $root = Parser::parse('a { :one: 1 }');
        $this->assertSame(' :', $root->first->first->raws->before);
    }

    public function testThrowsOnUnclosedBlocks()
    {
        $err = null;
        try {
            Parser::parse("\na {\n");
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':2:1: Unclosed block', $err->getMessage());
    }

    public function testThrowsOnUnnecessaryBlockClose()
    {
        $err = null;
        try {
            Parser::parse("a {\n} }");
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':2:3: Unexpected }', $err->getMessage());
    }

    public function testThrowsOnUnclosedComment()
    {
        $err = null;
        try {
            Parser::parse("\n/*\n ");
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':2:1: Unclosed comment', $err->getMessage());
    }

    public function testThrowsOnUnclosedQuote()
    {
        $err = null;
        try {
            Parser::parse("\n\"\n\na ");
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':2:1: Unclosed quote', $err->getMessage());
    }

    public function testThrowsOnUnclosedBracket()
    {
        $err = null;
        try {
            Parser::parse(':not(one() { }');
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':1:5: Unclosed bracket', $err->getMessage());
    }

    public function testThrowsOnPropertyWithoutValue()
    {
        $err = null;
        try {
            Parser::parse('a { b;}');
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':1:5: Unknown word', $err->getMessage());
        $err = null;
        try {
            Parser::parse('a { b b }');
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':1:5: Unknown word', $err->getMessage());
    }

    public function testThrowsOnNamelessAtRule()
    {
        $err = null;
        try {
            Parser::parse('@');
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':1:1: At-rule without name', $err->getMessage());
    }

    public function testThrowsOnPropertyWithoutSemicolon()
    {
        $err = null;
        try {
            Parser::parse('a { one: filter(a:"") two: 2 }');
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':1:21: Missed semicolon', $err->getMessage());
    }

    public function testThrowsOnDoubleColon()
    {
        $err = null;
        try {
            Parser::parse('a { one:: 1 }');
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains(':1:9: Double colon', $err->getMessage());
    }

    public function testDoesNotSuggestDifferentParsersForCSS()
    {
        $error;
        try {
            Parser::parse('a { one:: 1 }', ['from' => 'app.css']);
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertNotRegExp('/postcss-less|postcss-scss/', $err->getMessage());
    }

    public function testSuggestsPostcssScssForSCSSSources()
    {
        $err = null;
        try {
            Parser::parse('a { #{var}: 1 }', ['from' => 'app.scss']);
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains('postcss-scss', $err->getMessage());
    }

    public function testSuggestsPostcssLessForLessSources()
    {
        $err = null;
        try {
            Parser::parse('.@{my-selector} { }', ['from' => 'app.less']);
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $err);
        $this->assertContains('postcss-less', $err->getMessage());
    }
}
