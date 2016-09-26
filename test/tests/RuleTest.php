<?php

namespace PostCSS\Tests;

use PostCSS\Parser;
use PostCSS\Rule;

class RuleTest extends \PHPUnit_Framework_TestCase
{
    public function testInitializesWithProperties()
    {
        $rule = new Rule(['selector' => 'a']);
        $this->assertSame('a', $rule->selector);
    }

    public function testReturnsArrayInSelectors()
    {
        $rule = new Rule(['selector' => 'a,b']);
        $this->assertSame(['a', 'b'], $rule->selectors);
    }

    public function testTrimsSelectors()
    {
        $rule = new Rule(['selector' => ".a\n, .b  , .c"]);
        $this->assertSame(['.a', '.b', '.c'], $rule->selectors);
    }

    public function testIsSmartAboutSelectorsCommas()
    {
        $rule = new Rule([
            'selector' => '[foo=\'a, b\'], a:-moz-any(:focus, [href*=\',\'])',
        ]);
        $this->assertSame(
            ['[foo=\'a, b\']', 'a:-moz-any(:focus, [href*=\',\'])'],
            $rule->selectors
        );
    }

    public function testReceiveArrayInSelectors()
    {
        $rule = new Rule(['selector' => 'i, b']);
        $rule->selectors = ['em', 'strong'];
        $this->assertSame('em, strong', $rule->selector);
    }

    public function testSavesSeparatorInSelectors()
    {
        $rule = new Rule(['selector' => "i,\nb"]);
        $rule->selectors = ['em', 'strong'];
        $this->assertSame("em,\nstrong", $rule->selector);
    }

    public function testUsesBetweenToDetectSeparatorInSelectors()
    {
        $rule = new Rule(['selector' => 'b', 'raws' => ['between' => '']]);
        $rule->selectors = ['b', 'strong'];
        $this->assertSame('b,strong', $rule->selector);
    }

    public function testUsesSpaceInSeparatorBeDefaultInSelectors()
    {
        $rule = new Rule(['selector' => 'b']);
        $rule->selectors = ['b', 'strong'];
        $rule->_selector = 1;
        $this->assertSame('b, strong', $rule->selector);
    }

    public function testSelectorsWorksInConstructor()
    {
        $rule = new Rule(['selectors' => ['a', 'b']]);
        $this->assertSame('a, b', $rule->selector);
    }

    public function testInsertsDefaultSpaces()
    {
        $rule = new Rule(['selector' => 'a']);
        $this->assertSame('a {}', (string) $rule);
        $rule->append(['prop' => 'color', 'value' => 'black']);
        $this->assertSame("a {\n    color: black\n}", (string) $rule);
    }

    public function testClonesSpacesFromAnotherRule()
    {
        $root = Parser::parse("b{\n  }");
        //$S1 = (string) $root;
        $rule = new Rule(['selector' => 'em']);
        //$S2 = (string) $rule;
        $root->append($rule);
        //$S3 = (string) $root;
        $this->assertSame("b{\n  }\nem{\n  }", (string) $root);
    }

    public function testUsesDifferentSpacesForEmptyRules()
    {
        $root = Parser::parse("a{}\nb{\n a:1\n}");
        $rule = new Rule(['selector' => 'em']);
        $root->append($rule);
        $this->assertSame("a{}\nb{\n a:1\n}\nem{}", (string) $root);

        $rule->append(['prop' => 'top', 'value' => '0']);
        $this->assertSame("a{}\nb{\n a:1\n}\nem{\n top:0\n}", (string) $root);
    }
}
