<?php

namespace PostCSS\Tests;

use PostCSS\Parser;
use PostCSS\Result;

class RootTest extends \PHPUnit_Framework_TestCase
{
    public function testPrependFixesSpacesOnInsertBeforeFirst()
    {
        $css = Parser::parse('a {} b {}');
        $css->prepend(['selector' => 'em']);
        $this->assertSame('em {} a {} b {}', (string) $css);
    }

    public function testPrependFixesSpacesOnMultipleInsertsBeforeFirst()
    {
        $css = Parser::parse('a {} b {}');
        $css->prepend(['selector' => 'em'], ['selector' => 'strong']);
        $this->assertSame('em {} strong {} a {} b {}', (string) $css);
    }

    public function testPrependUsesDefaultSpacesOnOnlyFirst()
    {
        $css = Parser::parse('a {}');
        $css->prepend(['selector' => 'em']);
        $this->assertSame("em {}\na {}", (string) $css);
    }

    public function testAppendSetsNewLineBetweenRulesInMultilineFiles()
    {
        $a = Parser::parse("a {}\n\na {}\n");
        $b = Parser::parse("b {}\n");
        $this->assertSame("a {}\n\na {}\n\nb {}\n", (string) $a->append($b));
    }

    public function testAppendSetsNewLineBetweenRulesOnLastNewline()
    {
        $a = Parser::parse("a {}\n");
        $b = Parser::parse("b {}\n");
        $this->assertSame("a {}\nb {}\n", (string) $a->append($b));
    }

    public function testAppendSavesCompressedStyle()
    {
        $a = Parser::parse('a{}a{}');
        $b = Parser::parse("b {\n}\n");
        $this->assertSame('a{}a{}b{}', (string) $a->append($b));
    }

    public function testAppendSavesCompressedStyleWithMultipleNodes()
    {
        $a = Parser::parse('a{}a{}');
        $b = Parser::parse("b {\n}\n");
        $c = Parser::parse("c {\n}\n");
        $this->assertSame('a{}a{}b{}c{}', (string) $a->append($b, $c));
    }

    public function testInsertAfterDoesNotUseBeforeOfFirstRule()
    {
        $css = Parser::parse('a{} b{}');
        $css->insertAfter(0, ['selector' => '.a']);
        $css->insertAfter(2, ['selector' => '.b']);

        $this->assertNull($css->nodes[1]->raws->before);
        $this->assertSame(' ', $css->nodes[3]->raws->before);
        $this->assertSame('a{} .a{} b{} .b{}', (string) $css);
    }

    public function testFixesSpacesOnRemovingFirstRule()
    {
        $css = Parser::parse("a{}\nb{}\n");
        $css->first->remove();
        $this->assertSame("b{}\n", (string) $css);
    }

    public function testGeneratesResultWithMap()
    {
        $root = Parser::parse('a {}');
        $result = $root->toResult(['map' => true]);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertRegExp("/a \{\}\n\/\*# sourceMappingURL=/", $result->css);
    }
}
