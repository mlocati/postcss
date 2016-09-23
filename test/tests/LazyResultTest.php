<?php

namespace PostCSS\Tests;

use PostCSS\LazyResult;
use PostCSS\Processor;
use PostCSS\SourceMap\Generator;

class LazyResultTest extends \PHPUnit_Framework_TestCase
{
    protected static $processor = null;

    protected static function getProcessor()
    {
        if (static::$processor === null) {
            static::$processor = new Processor();
        }

        return static::$processor;
    }

    public function testContainsAST()
    {
        $result = new LazyResult(static::getProcessor(), 'a {}', []);
        $this->assertSame('root', $result->root->type);
    }

    public function testWillStringifyCSS()
    {
        $result = new LazyResult(static::getProcessor(), 'a {}', []);
        $this->assertSame('a {}', $result->css);
    }

    public function testStringifiesCSS()
    {
        $result = new LazyResult(static::getProcessor(), 'a {}', []);
        $this->assertSame($result->css, ''.$result);
    }

    public function testHasContentAliasForCss()
    {
        $result = new LazyResult(static::getProcessor(), 'a {}', []);
        $this->assertSame('a {}', $result->content);
    }

    public function testHasMapOnlyIfNecessary()
    {
        $result = new LazyResult(static::getProcessor(), '', []);
        $this->assertNull($result->map);

        $result = new LazyResult(static::getProcessor(), '', []);
        $this->assertNull($result->map);

        $result = new LazyResult(static::getProcessor(), '', ['map' => ['inline' => false]]);
        $this->assertInstanceOf(Generator::class, $result->map);
    }

    public function testContainsOptions()
    {
        $result = new LazyResult(static::getProcessor(), 'a {}', ['to' => 'a.css']);
        $this->assertSame(['to' => 'a.css'], $result->opts);
    }

    public function testContainsWarnings()
    {
        $result = new LazyResult(static::getProcessor(), 'a {}', []);
        $this->assertSame([], $result->warnings());
    }

    public function testContainsMessages()
    {
        $result = new LazyResult(static::getProcessor(), 'a {}', []);
        $this->assertSame([], $result->messages);
    }
}
