<?php

namespace PostCSS\Tests;

use PostCSS\AtRule;
use PostCSS\Parser;

class AtRuleTest extends \PHPUnit_Framework_TestCase
{
    public function testInitializesWithProperties()
    {
        $rule = new AtRule([
            'name' => 'encoding',
            'params' => '"utf-8"',
        ]);
        $this->assertSame('encoding', $rule->name);
        $this->assertSame('"utf-8"', $rule->params);
        $this->assertSame('@encoding "utf-8"', (string) $rule);
    }

    public function testDontFallOnChildlessAtRule()
    {
        $rule = new AtRule();
        $this->assertNull($rule->each(function ($i) {
            return $i;
        }));
    }

    public function testCreateNodesPropertyOnPrepend()
    {
        $rule = new AtRule();
        $this->assertNull($rule->nodes);
        $rule->prepend('color: black');
        $this->assertSame(1, count($rule->nodes));
    }

    public function testCreatesNodesPropertyOnAppend()
    {
        $rule = new AtRule();
        $this->assertNull($rule->nodes);

        $rule->append('color: black');
        $this->assertSame(1, count($rule->nodes));
    }

    public function testInsertsDefaultSpaces()
    {
        $rule = new AtRule([
            'name' => 'page',
            'params' => 1,
            'nodes' => [],
        ]);
        $this->assertSame('@page 1 {}', (string) $rule);
    }

    public function testCloneSpacesFromAnotherAtRule()
    {
        $root = Parser::parse('@page{}a{}');
        $rule = new AtRule([
            'name' => 'page',
            'params' => 1,
            'nodes' => [],
        ]);
        $root->append($rule);

        $this->assertSame('@page 1{}', (string) $rule);
    }
}
