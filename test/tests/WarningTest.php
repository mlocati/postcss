<?php

namespace PostCSS\Tests;

use PostCSS\Declaration;
use PostCSS\Path\NodeJS as Path;
use PostCSS\Parser;
use PostCSS\Warning;

class WarningTest extends \PHPUnit_Framework_TestCase
{
    public function testOutputsSimpleWarning()
    {
        $warning = new Warning('text');
        $this->assertSame('text', (string) $warning);
    }

    public function testOutputsWarningWithPlugin()
    {
        $warning = new Warning('text', ['plugin' => 'plugin']);
        $this->assertSame('plugin: text', (string) $warning);
    }

    public function testOutputsWarningWithPosition()
    {
        $root = Parser::parse('a{}');
        $warning = new Warning('text', ['node' => $root->first]);
        $this->assertSame('<css input>:1:1: text', (string) $warning);
    }

    public function testOutputsWarningWithPluginAndNode()
    {
        $file = Path::resolve('a.css');
        $root = Parser::parse('a{}', ['from' => $file]);
        $warning = new Warning(
            'text',
            [
                'plugin' => 'plugin',
                'node' => $root->first,
            ]
        );
        $this->assertSame("plugin: $file:1:1: text", (string) $warning);
    }

    public function testOutputsWarningWithIndex()
    {
        $file = Path::resolve('a.css');
        $root = Parser::parse('@rule param {}', ['from' => $file]);
        $warning = new Warning(
            'text',
            [
                'plugin' => 'plugin',
                'node' => $root->first,
                'index' => 7,
            ]
        );
        $this->assertSame("plugin: $file:1:8: text", (string) $warning);
    }

    public function testOutputsWarningWithWord()
    {
        $file = Path::resolve('a.css');
        $root = Parser::parse('@rule param {}', ['from' => $file]);
        $warning = new Warning(
            'text',
            [
                'plugin' => 'plugin',
                'node' => $root->first,
                'word' => 'am',
            ]
        );
        $this->assertSame("plugin: $file:1:10: text", (string) $warning);
    }

    public function testGeneratesWarningWithoutSource()
    {
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);
        $warning = new Warning('text', ['node' => $decl]);
        $this->assertSame('<css input>: text', (string) $warning);
    }

    public function testHasLineAndColumnIsUndefinedByDefault()
    {
        $warning = new Warning('text');
        $this->assertNull($warning->line);
        $this->assertNull($warning->column);
    }

    public function testGetsPositionFromNode()
    {
        $root = Parser::parse('a{}');
        $warning = new Warning('text', ['node' => $root->first]);
        $this->assertSame(1, $warning->line);
        $this->assertSame(1, $warning->column);
    }

    public function testGetsPositionFromWord()
    {
        $root = Parser::parse('a b{}');
        $warning = new Warning('text', ['node' => $root->first, 'word' => 'b']);
        $this->assertSame(1, $warning->line);
        $this->assertSame(3, $warning->column);
    }

    public function testGetsPositionFromIndex()
    {
        $root = Parser::parse('a b{}');
        $warning = new Warning('text', ['node' => $root->first, 'index' => 2]);
        $this->assertSame(1, $warning->line);
        $this->assertSame(3, $warning->column);
    }
}
