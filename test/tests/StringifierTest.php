<?php

namespace PostCSS\Tests;

use PostCSS\AtRule;
use PostCSS\Declaration;
use PostCSS\Node;
use PostCSS\Root;
use PostCSS\Rule;
use PostCSS\Stringifier;
use PostCSS\Parser;

class StringifierTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Stringifier
     */
    protected $str;

    protected function setUp()
    {
        parent::setUp();
        $this->str = new Stringifier();
    }

    public function testCreatesTrimmedRawProperty()
    {
        $b = new Node(['one' => 'trim']);
        $b->raws->one = ['value' => 'trim', 'raw' => 'raw'];
        $this->assertSame('raw', $this->str->rawValue($b, 'one'));

        $b = new Node(['one' => 'trim']);
        $b->raws->one = (object) ['value' => 'trim', 'raw' => 'raw'];
        $this->assertSame('raw', $this->str->rawValue($b, 'one'));

        $b->one = 'trim1';
        $this->assertSame('trim1', $this->str->rawValue($b, 'one'));
    }

    public function testWorksWithoutRawValueMagic()
    {
        $b = new Node();
        $b->one = '1';
        $this->assertSame('1', $b->one);
        $this->assertSame('1', $this->str->rawValue($b, 'one'));
    }

    public function testUsesNodeRaw()
    {
        $rule = new Rule(['selector' => 'a', 'raws' => ['between' => "\n"]]);
        $this->assertSame("\n", $this->str->raw($rule, 'between', 'beforeOpen'));
    }

    public function testHacksBeforeForNodesWithoutParent()
    {
        $rule = new Rule(['selector' => 'a']);
        $this->assertSame('', $this->str->raw($rule, 'before'));
    }

    public function testHacksBeforeForFirstNode()
    {
        $root = new Root();
        $root->append(new Rule(['selector' => 'a']));
        $this->assertSame('', $this->str->raw($root->first, 'before'));
    }

    public function testHacksBeforeForFirstDecl()
    {
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);
        $this->assertSame('', $this->str->raw($decl, 'before'));

        $rule = new Rule(['selector' => 'a']);
        $rule->append($decl);
        $this->assertSame("\n    ", $this->str->raw($decl, 'before'));
    }

    public function testDetectsAfterRaw()
    {
        $root = new Root();
        $root->append(['selector' => 'a', 'raws' => ['after' => ' ']]);
        $root->first->append(['prop' => 'color', 'value' => 'black']);
        $root->append(['selector' => 'a']);
        $this->assertSame(' ', $this->str->raw($root->last, 'after'));
    }

    public function testUsesDefaultsWithoutParent()
    {
        $rule = new Rule(['selector' => 'a']);
        $this->assertSame(' ', $this->str->raw($rule, 'between', 'beforeOpen'));
    }

    public function testUsesDefaultsForUniqueNode()
    {
        $root = new Root();
        $root->append(new Rule(['selector' => 'a']));
        $this->assertSame(' ', $this->str->raw($root->first, 'between', 'beforeOpen'));
    }

    public function testClonesRawFromFirstNode()
    {
        $root = new Root();
        $root->append(new Rule(['selector' => 'a', 'raws' => ['between' => '']]));
        $root->append(new Rule(['selector' => 'b']));

        $this->assertSame('', $this->str->raw($root->last, 'between', 'beforeOpen'));
    }

    public function testIndentsByDefault()
    {
        $root = new Root();
        $root->append(new AtRule(['name' => 'page']));
        $root->first->append(new Rule(['selector' => 'a']));
        $root->first->first->append(['prop' => 'color', 'value' => 'black']);

        $this->assertSame("@page {\n    a {\n        color: black\n    }\n}", (string) $root);
    }

    public function testClonesStyle()
    {
        $compress = Parser::parse('@page{ a{ } }');
        $spaces = Parser::parse("@page {\n  a {\n  }\n}");

        $compress->first->first->append(['prop' => 'color', 'value' => 'black']);
        $this->assertSame('@page{ a{ color: black } }', (string) $compress);

        $spaces->first->first->append(['prop' => 'color', 'value' => 'black']);
        $this->assertSame("@page {\n  a {\n    color: black\n  }\n}", (string) $spaces);
    }

    public function testClonesIndent()
    {
        $root = Parser::parse("a{\n}");
        $root->first->append(['text' => 'a']);
        $root->first->append(['text' => 'b', 'raws' => ['before' => "\n\n "]]);
        $this->assertSame("a{\n\n /* a */\n\n /* b */\n}", (string) $root);
    }

    public function testClonesDeclarationBeforeForComment()
    {
        $root = Parser::parse("a{\n}");
        $root->first->append(['text' => 'a']);
        $root->first->append([
            'prop' => 'a',
            'value' => '1',
            'raws' => ['before' => "\n\n "],
        ]);
        $this->assertSame("a{\n\n /* a */\n\n a: 1\n}", (string) $root);
    }

    public function testClonesIndentByTypes()
    {
        $css = Parser::parse("a {\n  color: black\n}\n\nb {\n}");
        $css->append(new Rule(['selector' => 'em']));
        $css->last->append(['prop' => 'z-index', 'value' => '1']);

        $this->assertSame("\n\n", $css->last->raw('before'));
        $this->assertSame("\n  ", $css->last->first->raw('before'));
    }

    public function testClonesIndentByBeforeAndAfter()
    {
        $css = Parser::parse("@page{\n\n a{\n  color: black}}");
        $css->first->append(new Rule(['selector' => 'b']));
        $css->first->last->append(['prop' => 'z-index', 'value' => '1']);

        $this->assertSame("\n\n ", $css->first->last->raw('before'));
        $this->assertSame('', $css->first->last->raw('after'));
    }

    public function testClonesSemicolonOnlyFromRulesWithChildren()
    {
        $css = Parser::parse('a{}b{one:1;}');
        $this->assertTrue($this->str->raw($css->first, 'semicolon'));
    }

    public function testClonesOnlySpacesInBefore()
    {
        $css = Parser::parse('a{*one:1}');
        $css->first->append(['prop' => 'two', 'value' => '2']);
        $css->append(['name' => 'keyframes', 'params' => 'a']);
        $css->last->append(['selector' => 'from']);
        $this->assertSame("a{*one:1;two:2}\n@keyframes a{\nfrom{}}", (string) $css);
    }

    public function testClonesOnlySpacesInBetween()
    {
        $css = Parser::parse('a{one/**/:1}');
        $css->first->append(['prop' => 'two', 'value' => '2']);
        $this->assertSame('a{one/**/:1;two:2}', (string) $css);
    }

    public function testUsesOptionalRawsIndent()
    {
        $rule = new Rule(['selector' => 'a', 'raws' => ['indent' => ' ']]);
        $rule->append(['prop' => 'color', 'value' => 'black']);
        $this->assertSame("a {\n color: black\n}", (string) $rule);
    }
}
