<?php

namespace PostCSS\Tests;

use PostCSS\Declaration;
use PostCSS\Parser;
use PostCSS\Rule;
use PostCSS\Root;
use PostCSS\Exception\CssSyntaxError;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    private static function getExample()
    {
        return implode('', [
            'a { a: 1; b: 2 }',
            '/* a */',
            '@keyframes anim {',
            '/* b */',
            'to { c: 3 }',
            '}',
            '@media all and (min-width: 100) {',
            'em { d: 4 }',
            '@page {',
            'e: 5;',
            '/* c */',
            '}',
            '}',
        ]);
    }

    public function testThrowsErrorOnDeclarationWithoutValue()
    {
        $rule = new Rule();
        $err = null;
        try {
            $rule->append(['prop' => 'color', 'vlaue' => 'black']);
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $x);
        $this->assertContains('Value field is missed', $err->getMessage());
    }

    public function testThrowsErrorOnUnknownNodeType()
    {
        $rule = new Rule();
        $err = null;
        try {
            $rule->append(['foo' => 'bar']);
        } catch (CssSyntaxError $x) {
            $err = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $x);
        $this->assertContains('Unknown node type', $err->getMessage());
    }

    public function testPushAddsChildWithoutChecks()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->push(new Declaration(['prop' => 'c', 'value' => '3']));
        $this->assertSame('a { a: 1; b: 2; c: 3 }', (string) $rule);
        $this->assertSame(3, count($rule->nodes));
        $this->assertFalse(property_exists($rule->last->raws, 'before'));
    }

    public function testEachIterates()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $indexes = [];

        $test = $this;
        $result = $rule->each(function ($decl, $i) use ($rule, &$indexes, $test) {
            $indexes[] = $i;
            $test->assertSame($decl, $rule->nodes[$i]);
        });
        $this->assertNull($result);
        $this->assertSame([0, 1], $indexes);
    }

    public function testEachIteratesWithPrepend()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $size = 0;

        $rule->each(function () use ($rule, &$size) {
            $rule->prepend(['prop' => 'color', 'value' => 'aqua']);
            $size += 1;
        });

        $this->assertSame(2, $size);
    }

    public function testEachIteratesWithPrependInsertBefore()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $size = 0;

        $rule->each(function ($decl) use ($rule, &$size) {
            if ($decl->prop === 'a') {
                $rule->insertBefore($decl, ['prop' => 'c', 'value' => '3']);
            }
            $size += 1;
        });
        $this->assertSame(2, $size);
    }

    public function testEachIteratesWithAppendInsertBefore()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $size = 0;
        $rule->each(function ($decl, $i) use ($rule, &$size) {
            if ($decl->prop === 'a') {
                $rule->insertBefore($i + 1, ['prop' => 'c', 'value' => '3']);
            }
            $size += 1;
        });
        $this->assertSame(3, $size);
    }

    public function testEachIteratesWithPrependInsertAfter()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $size = 0;

        $rule->each(function ($decl, $i) use ($rule, &$size) {
            $rule->insertAfter($i - 1, ['prop' => 'c', 'value' => '3']);
            $size += 1;
        });
        $this->assertSame(2, $size);
    }

    public function testEachIteratesWithAppendInsertAfter()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $size = 0;
        $rule->each(function ($decl, $i) use ($rule, &$size) {
            if ($decl->prop === 'a') {
                $rule->insertAfter($i, ['prop' => 'c', 'value' => '3']);
            }
            $size += 1;
        });
        $this->assertSame(3, $size);
    }

    public function testEachIteratesWithRemove()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $size = 0;
        $rule->each(function () use ($rule, &$size) {
            $rule->removeChild(0);
            $size += 1;
        });
        $this->assertSame(2, $size);
    }

    public function testEachBreaksIteration()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $indexes = [];

        $result = $rule->each(function ($decl, $i) use (&$indexes) {
            $indexes[] = $i;

            return false;
        });
        $this->assertSame(false, $result);
        $this->assertSame([0], $indexes);
    }

    public function testEachAllowsToChangeChildren()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $props = [];

        $rule->each(function ($decl) use (&$props, $rule) {
            $props[] = $decl->prop;
            $rule->nodes = [$rule->last, $rule->first];
        });
        $this->assertSame(['a', 'a'], $props);
    }

    public function testWalkIterates()
    {
        $types = [];
        $indexes = [];

        $result = Parser::parse(self::getExample())->walk(function ($node, $i) use (&$types, &$indexes) {
            $types[] = $node->type;
            $indexes[] = $i;
        });

        $this->assertNull($result);
        $this->assertSame(['rule', 'decl', 'decl', 'comment', 'atrule', 'comment', 'rule', 'decl', 'atrule', 'rule', 'decl', 'atrule', 'decl', 'comment'], $types);
        $this->assertSame([0, 0, 1, 1, 2, 0, 1, 0, 3, 0, 0, 1, 0, 1], $indexes);
    }

    public function testWalkBreaksIteration()
    {
        $indexes = [];

        $result = Parser::parse(self::getExample())->walk(function ($decl, $i) use (&$indexes) {
            $indexes[] = $i;

            return false;
        });

        $this->assertSame(false, $result);
        $this->assertSame([0], $indexes);
    }

    public function testWalkDeclsIterates()
    {
        $props = [];
        $indexes = [];

        $result = Parser::parse(self::getExample())->walkDecls(function ($decl, $i) use (&$props, &$indexes) {
            $props[] = $decl->prop;
            $indexes[] = $i;
        });
        $this->assertNull($result);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $props);
        $this->assertSame([0, 1, 0, 0, 0], $indexes);
    }

    public function testWalkDeclsIteratesWithChanges()
    {
        $size = 0;
        Parser::parse(self::getExample())->walkDecls(function ($decl, $i) use (&$size) {
            $decl->parent->removeChild($i);
            $size += 1;
        });
        $this->assertSame(5, $size);
    }

    public function testWalkDeclsBreaksIteration()
    {
        $indexes = [];
        $result = Parser::parse(self::getExample())->walkDecls(function ($decl, $i) use (&$indexes) {
            $indexes[] = $i;

            return false;
        });
        $this->assertSame(false, $result);
        $this->assertSame([0], $indexes);
    }

    public function testWalkDeclsFiltersDeclarationsByPropertyName()
    {
        $me = $this;
        $css = Parser::parse('@page{a{one:1}}b{one:1;two:2}');
        $size = 0;
        $css->walkDecls('one', function ($decl) use ($me, &$size) {
            $this->assertSame('one', $decl->prop);
            $size += 1;
        });

        $this->assertSame(2, $size);
    }

    public function testWalkDeclsBreaksDeclarationsFilterByName()
    {
        $css = Parser::parse('@page{a{one:1}}b{one:1;two:2}');
        $size = 0;
        $css->walkDecls('one', function () use (&$size) {
            $size += 1;

            return false;
        });

        $this->assertSame(1, $size);
    }

    public function testWalkDeclsFiltersDeclarationsByPropertyRegexp()
    {
        $css = Parser::parse('@page{a{one:1}}b{one-x:1;two:2}');
        $size = 0;
        $css->walkDecls('/one(-x)?/', function () use (&$size) {
            $size += 1;
        });
        $this->assertSame(2, $size);
    }

    public function testWalkDeclsBreaksDeclarationsFiltersByRegexp()
    {
        $css = Parser::parse('@page{a{one:1}}b{one-x:1;two:2}');
        $size = 0;
        $css->walkDecls('/one(-x)?/', function () use (&$size) {
            $size += 1;

            return false;
        });
        $this->assertSame(1, $size);
    }

    public function testWalkCommentsIterates()
    {
        $texts = [];
        $indexes = [];

        $result = Parser::parse(self::getExample())->walkComments(function ($comment, $i) use (&$texts, &$indexes) {
            $texts[] = $comment->text;
            $indexes[] = $i;
        });
        $this->assertNull($result);
        $this->assertSame(['a', 'b', 'c'], $texts);
        $this->assertSame([1, 0, 1], $indexes);
    }

    public function testWalkCommentsIteratesWithChanges()
    {
        $size = 0;
        Parser::parse(self::getExample())->walkComments(function ($comment, $i) use (&$size) {
            $comment->parent->removeChild($i);
            $size += 1;
        });
        $this->assertSame(3, $size);
    }

    public function testWalkCommentsBreaksIteration()
    {
        $indexes = [];

        $result = Parser::parse(self::getExample())->walkComments(function ($comment, $i) use (&$indexes) {
            $indexes[] = $i;

            return false;
        });

        $this->assertSame(false, $result);
        $this->assertSame([1], $indexes);
    }

    public function testWalkRulesIterates()
    {
        $selectors = [];
        $indexes = [];
        $result = Parser::parse(self::getExample())->walkRules(function ($rule, $i) use (&$selectors, &$indexes) {
            $selectors[] = $rule->selector;
            $indexes[] = $i;
        });
        $this->assertNull($result);
        $this->assertSame(['a', 'to', 'em'], $selectors);
        $this->assertSame([0, 1, 0], $indexes);
    }

    public function testWalkRulesIteratesWithChanges()
    {
        $size = 0;
        Parser::parse(self::getExample())->walkRules(function ($rule, $i) use (&$size) {
            $rule->parent->removeChild($i);
            $size += 1;
        });
        $this->assertSame(3, $size);
    }

    public function testWalkRulesBreaksIteration()
    {
        $indexes = [];

        $result = Parser::parse(self::getExample())->walkRules(function ($rule, $i) use (&$indexes) {
            $indexes[] = $i;

            return false;
        });
        $this->assertSame(false, $result);
        $this->assertSame([0], $indexes);
    }

    public function testWalkRulesFiltersBySelector()
    {
        $me = $this;
        $size = 0;
        Parser::parse('a{}b{}a{}')->walkRules('a', function ($rule) use ($me, &$size) {
            $me->assertSame('a', $rule->selector);
            $size += 1;
        });
        $this->assertSame(2, $size);
    }

    public function testWalkRulesBreaksSelectorFilters()
    {
        $size = 0;
        Parser::parse('a{}b{}a{}')->walkRules('a', function () use (&$size) {
            $size += 1;

            return false;
        });
        $this->assertSame(1, $size);
    }

    public function testWalkRulesFiltersByRegexp()
    {
        $me = $this;
        $size = 0;
        Parser::parse('a{}a b{}b a{}')->walkRules('/^a/', function ($rule) use ($me, &$size) {
            $me->assertRegExp('/^a/', $rule->selector);
            $size += 1;
        });
        $this->assertSame(2, $size);
    }

    public function testWalkRulesBreaksSelectorRegexp()
    {
        $size = 0;
        Parser::parse('a{}b a{}b a{}')->walkRules('/^a/', function () use (&$size) {
            $size += 1;

            return false;
        });
        $this->assertSame(1, $size);
    }

    public function testWalkAtRulesIterates()
    {
        $names = [];
        $indexes = [];

        $result = Parser::parse(self::getExample())->walkAtRules(function ($atrule, $i) use (&$names, &$indexes) {
            $names[] = $atrule->name;
            $indexes[] = $i;
        });

        $this->assertNull($result);
        $this->assertSame(['keyframes', 'media', 'page'], $names);
        $this->assertSame([2, 3, 1], $indexes);
    }

    public function testWalkAtRulesIteratesWithChanges()
    {
        $size = 0;
        Parser::parse(self::getExample())->walkAtRules(function ($atrule, $i) use (&$size) {
            $atrule->parent->removeChild($i);
            $size += 1;
        });
        $this->assertSame(3, $size);
    }

    public function testWalkAtRulesBreaksIteration()
    {
        $indexes = [];

        $result = Parser::parse(self::getExample())->walkAtRules(function ($atrule, $i) use (&$indexes) {
            $indexes[] = $i;

            return false;
        });

        $this->assertSame(false, $result);
        $this->assertSame([2], $indexes);
    }

    public function testWalkAtRulesFiltersAtRulesByName()
    {
        $me = $this;
        $css = Parser::parse('@page{@page 2{}}@media print{@page{}}');
        $size = 0;

        $css->walkAtRules('page', function ($atrule) use ($me, &$size) {
            $me->assertSame('page', $atrule->name);
            $size += 1;
        });

        $this->assertSame(3, $size);
    }

    public function testWalkAtRulesBreaksNameFilter()
    {
        $size = 0;
        Parser::parse('@page{@page{@page{}}}')->walkAtRules('page', function () use (&$size) {
            $size += 1;

            return false;
        });
        $this->assertSame(1, $size);
    }

    public function testWalkAtRulesFiltersAtRulesByNameRegexp()
    {
        $css = Parser::parse('@page{@page 2{}}@media print{@pages{}}');
        $size = 0;

        $css->walkAtRules('/page/', function () use (&$size) {
            $size += 1;
        });

        $this->assertSame(3, $size);
    }

    public function testWalkAtRulesBreaksRegexpFilter()
    {
        $size = 0;
        Parser::parse('@page{@pages{@page{}}}')->walkAtRules('/page/', function () use (&$size) {
            $size += 1;

            return false;
        });
        $this->assertSame(1, $size);
    }

    public function testAppendAppendsChild()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->append(['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; b: 2; c: 3 }', (string) $rule);
        $this->assertSame(' ', $rule->last->raws->before, ' ');
    }

    public function testAppendAppendsMultipleChildren()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->append(['prop' => 'c', 'value' => '3'], ['prop' => 'd', 'value' => '4']);
        $this->assertSame('a { a: 1; b: 2; c: 3; d: 4 }', (string) $rule);
        $this->assertSame(' ', $rule->last->raws->before, ' ');
    }

    public function testAppendHasDeclarationShortcut()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->append(['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; b: 2; c: 3 }', (string) $rule);
    }

    public function testAppendHasRuleShortcut()
    {
        $root = new Root();
        $root->append(['selector' => 'a']);
        $this->assertSame('a {}', (string) $root->first);
    }

    public function testAppendHasAtRuleShortcut()
    {
        $root = new Root();
        $root->append(['name' => 'encoding', 'params' => '"utf-8"']);
        $this->assertSame('@encoding "utf-8"', (string) $root->first);
    }

    public function testAppendHasCommentShortcut()
    {
        $root = new Root();
        $root->append(['text' => 'ok']);
        $this->assertSame('/* ok */', (string) $root->first);
    }

    public function testAppendReceivesRoot()
    {
        $css = Parser::parse('a {}');
        $css->append(Parser::parse('b {}'));
        $this->assertSame("a {}\nb {}", (string) $css);
    }

    public function testAppendReveivesString()
    {
        $root = new Root();
        $root->append('a{}b{}');
        $root->first->append('color:black');
        $this->assertSame("a {\n    color: black\n}\nb {}", (string) $root);
        $this->assertNull($root->first->first->source);
    }

    public function testAppendReceivesArray()
    {
        $a = Parser::parse('a{ z-index: 1 }');
        $b = Parser::parse('b{width:1px;height:2px}');

        $a->first->append($b->first->nodes);
        $this->assertSame('a{ z-index: 1; width: 1px; height: 2px }', (string) $a);
        $this->assertSame('b{width:1px;height:2px}', (string) $b);
    }

    public function testAppendClonesNodeOnInsert()
    {
        $a = Parser::parse('a{}');
        $b = Parser::parse('b{}');

        $b->append($a->first);
        $b->last->selector = 'b a';

        $this->assertSame('a{}', (string) $a);
        $this->assertSame("b{}\nb a{}", (string) $b);
    }

    public function testPrependPrependsChild()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->prepend(['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { c: 3; a: 1; b: 2 }', (string) $rule);
        $this->assertSame(' ', $rule->first->raws->before);
    }

    public function testPrependPrependsMultipleChildren()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->prepend(['prop' => 'c', 'value' => '3'], ['prop' => 'd', 'value' => '4']);
        $this->assertSame('a { c: 3; d: 4; a: 1; b: 2 }', (string) $rule);
        $this->assertSame(' ', $rule->first->raws->before);
    }

    public function testPrependReceiveHashInsteadOfDeclaration()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->prepend(['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { c: 3; a: 1; b: 2 }', (string) $rule);
    }

    public function testPrependReceivesRoot1()
    {
        $css = Parser::parse('a {}');
        $css->prepend(Parser::parse('b {}'));
        $this->assertSame("b {}\na {}", (string) $css);
    }

    public function testPrependReceivesRoot2()
    {
        $css = Parser::parse('a {}');
        $css->prepend('b {}');
        $this->assertSame("b {}\na {}", (string) $css);
    }

    public function testPrependReceivesArray()
    {
        $a = Parser::parse('a{ z-index: 1 }');
        $b = Parser::parse('b{width:1px;height:2px}');

        $a->first->prepend($b->first->nodes);
        $this->assertSame('a{ width: 1px; height: 2px; z-index: 1 }', (string) $a);
    }

    public function testPrependWorksOnEmptyContainer()
    {
        $root = Parser::parse('');
        $root->prepend(new Rule(['selector' => 'a']));
        $this->assertSame('a {}', (string) $root);
    }

    public function testInsertBeforeInsertsChild()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->insertBefore(1, ['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; c: 3; b: 2 }', (string) $rule);
        $this->assertSame(' ', $rule->nodes[1]->raws->before);
    }

    public function testInsertBeforeWorksWithNodesToo()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->insertBefore($rule->nodes[1], ['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; c: 3; b: 2 }', (string) $rule);
    }

    public function testInsertBeforeReceiveHashInsteadOfDeclaration()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->insertBefore(1, ['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; c: 3; b: 2 }', (string) $rule);
    }

    public function testInsertBeforeReceivesArray()
    {
        $a = Parser::parse('a{ color: red; z-index: 1 }');
        $b = Parser::parse('b{width:1;height:2}');

        $a->first->insertBefore(1, $b->first->nodes);
        $this->assertSame('a{ color: red; width: 1; height: 2; z-index: 1 }', (string) $a);
    }

    public function testInsertAfterInsertsChild()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->insertAfter(0, ['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; c: 3; b: 2 }', (string) $rule);
        $this->assertSame(' ', $rule->nodes[1]->raws->before);
    }

    public function testInsertAfterWorksWithNodesToo()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->insertAfter($rule->first, ['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; c: 3; b: 2 }', (string) $rule);
    }

    public function testInsertAfterReceiveHashInsteadOfDeclaration()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->insertAfter(0, ['prop' => 'c', 'value' => '3']);
        $this->assertSame('a { a: 1; c: 3; b: 2 }', (string) $rule);
    }

    public function testInsertAfterReceivesArray()
    {
        $a = Parser::parse('a{ color: red; z-index: 1 }');
        $b = Parser::parse('b{width:1;height:2}');

        $a->first->insertAfter(0, $b->first->nodes);
        $this->assertSame('a{ color: red; width: 1; height: 2; z-index: 1 }', (string) $a);
    }

    public function testRemoveChildRemovesByIndex()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->removeChild(1);
        $this->assertSame('a { a: 1 }', (string) $rule);
    }

    public function testRemoveChildRemovesByNode()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->removeChild($rule->last);
        $this->assertSame('a { a: 1 }', (string) $rule);
    }

    public function testRemoveChildCleansParentInRemovedNode()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $decl = $rule->first;
        $rule->removeChild($decl);
        $this->assertNull($decl->parent);
    }

    public function testRemoveAllRemovesAllChildren()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $decl = $rule->first;
        $rule->removeAll();

        $this->assertNull($decl->parent);
        $this->assertSame('a { }', (string) $rule);
    }

    public function testReplaceValuesReplacesStrings()
    {
        $css = Parser::parse('a{one:1}b{two:1 2}');
        $result = $css->replaceValues('1', 'A');

        $this->assertSame($css, $result);
        $this->assertSame('a{one:A}b{two:A 2}', (string) $css);
    }

    public function testReplaceValuesReplacesRegpexp()
    {
        $css = Parser::parse('a{one:1}b{two:1 2}');
        $css->replaceValues('/\d/', function ($i) {
            return $i.'A';
        });
        $this->assertSame('a{one:1A}b{two:1A 2A}', (string) $css);
    }

    public function testReplaceValuesFiltersProperties()
    {
        $css = Parser::parse('a{one:1}b{two:1 2}');
        $css->replaceValues('1', ['props' => ['one']], 'A');
        $this->assertSame('a{one:A}b{two:1 2}', (string) $css);
    }

    public function testReplaceValuesUsesFastCheck()
    {
        $css = Parser::parse('a{one:1}b{two:1 2}');
        $css->replaceValues('1', ['fast' => '2'], 'A');
        $this->assertSame('a{one:1}b{two:A 2}', (string) $css);
    }

    public function testAnyReturnTrueIfAllChildrenReturnTrue()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $this->assertSame(true, $rule->every(function ($i) {
            return preg_match('/a|b/', $i->prop);
        }));
        $this->assertSame(false, $rule->every(function ($i) {
            return preg_match('/b/', $i->prop);
        }));
    }

    public function testSomeReturnTrueIfAllChildrenReturnTrue()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $this->assertSame(true, $rule->some(function ($i) {
            return $i->prop === 'b';
        }));
        $this->assertSame(false, $rule->some(function ($i) {
            return $i->prop === 'c';
        }));
    }

    public function testIndexReturnsChildIndex()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $this->assertSame(1, $rule->index($rule->nodes[1]));
    }

    public function testIndexReturnsArgumentIfItIsNumber()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $this->assertSame(2, $rule->index(2));
    }

    public function testReturnsFirstChild()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $this->assertSame('a', $rule->first->prop);
    }

    public function testReturnsLastChild()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $this->assertSame('b', $rule->last->prop);
    }

    public function testNormalizeDoesNotNormalizeNewChildrenWithExistsBefore()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->append(['prop' => 'c', 'value' => '3', 'raws' => ['before' => "\n "]]);
        $this->assertSame("a { a: 1; b: 2;\n c: 3 }", (string) $rule);
    }

    public function testForcesDeclarationValueToBeString()
    {
        $rule = Parser::parse('a { a: 1; b: 2 }')->first;
        $rule->append(['prop' => 'c', 'value' => 3]);
        $this->assertInternalType('string', $rule->first->value);
        $this->assertInternalType('string', $rule->last->value);
    }
}
