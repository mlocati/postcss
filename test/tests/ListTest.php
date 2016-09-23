<?php

namespace PostCSS\Tests;

use PostCSS\ListUtil;

class ListTest extends \PHPUnit_Framework_TestCase
{
    public function testSpaceSplitsListBySpaces()
    {
        $this->assertSame(['a', 'b'], ListUtil::space('a b'));
    }

    public function testSpaceTrimsValues()
    {
        $this->assertSame(['a', 'b'], ListUtil::space(' a  b '));
    }

    public function testSpaceChecksQuotes()
    {
        $this->assertSame(['"a b\\""', '\'\''], ListUtil::space('"a b\\"" \'\''));
    }

    public function testSpaceChecksFunctions()
    {
        $this->assertSame(['f( ))', 'a( () )'], ListUtil::space('f( )) a( () )'));
    }

    public function testSpaceWorksFromVariable()
    {
        $this->assertSame(['a', 'b'], ListUtil::space('a b'));
    }

    public function testCommaSplitsListBySpaces()
    {
        $this->assertSame(['a', 'b'], ListUtil::comma('a, b'));
    }

    public function testCommaAddsLastEmpty()
    {
        $this->assertSame(['a', 'b', ''], ListUtil::comma('a, b,'));
    }

    public function testCommaChecksQuotes()
    {
        $this->assertSame(['"a,b\\""', '\'\''], ListUtil::comma('"a,b\\"", \'\''));
    }

    public function testCommaChecksFunctions()
    {
        $this->assertSame(['f(,))', 'a(,(),)'], ListUtil::comma('f(,)), a(,(),)'));
    }

    public function testCommaWorksFromVariable()
    {
        $this->assertSame(['a', 'b'], ListUtil::comma('a, b'));
    }
}
