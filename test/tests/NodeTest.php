<?php

namespace PostCSS\Tests;

use PostCSS\Exception\CssSyntaxError;
use PostCSS\Plugin\ClosurePlugin;
use PostCSS\Declaration;
use PostCSS\AtRule;
use PostCSS\Root;
use PostCSS\Node;
use PostCSS\Rule;
use PostCSS\Path\NodeJS as Path;
use PostCSS\Parser;
use PostCSS\Processor;
use PostCSS\Warning;
use React\Promise\FulfilledPromise;

class NodeTest extends \PHPUnit_Framework_TestCase
{
    public static function stringify(Node $node, callable $builder)
    {
        $builder($node->selector);
    }

    public function testErrorGeneratesCustomError()
    {
        $file = Path::resolve('a.css');
        $css = Parser::parse('a{}', ['from' => $file]);
        $error = $css->first->error('Test');
        $this->assertInstanceOf(CssSyntaxError::class, $error);
        $this->assertSame($file.':1:1: Test', $error->getMessage());
    }

    public function testErrorGeneratesCustomErrorForNodesWithoutSource()
    {
        $rule = new Rule(['selector' => 'a']);
        $error = $rule->error('Test');
        $this->assertSame('<css input>: Test', $error->getMessage());
    }

    public function testErrorHighlightsIndex()
    {
        $root = Parser::parse('a { b: c }');
        $error = $root->first->first->error('Bad semicolon', ['index' => 1]);
        $this->assertSame("> 1 | a { b: c }\n    |      ^", $error->showSourceCode(false));
    }

    public function testErrorHighlightsWord()
    {
        $root = Parser::parse('a { color: x red }');
        $error = $root->first->first->error('Wrong color', ['word' => 'x']);
        $this->assertSame("> 1 | a { color: x red }\n    |            ^", $error->showSourceCode(false));
    }

    public function testErrorHighlightsWordInMultilineString()
    {
        $root = Parser::parse("a { color: red\n           x }");
        $error = $root->first->first->error('Wrong color', ['word' => 'x']);
        $this->assertSame("  1 | a { color: red\n> 2 |            x }\n    |            ^", $error->showSourceCode(false));
    }

    public function testWarnAttachesAWarningToTheResultObject()
    {
        $warning = null;
        $warner = new ClosurePlugin(
            function ($css, $result) use (&$warning) {
                $warning = $css->first->warn($result, 'FIRST!');
            },
            'warner'
        );

        $me = $this;
        $result = (new Processor([$warner]))->process('a{}')->then(
            function ($result) use ($me, &$warning) {
                $me->assertInstanceOf(Warning::class, $warning);
                $me->assertSame('FIRST!', $warning->text);
                $me->assertSame('warner', $warning->plugin);
                $me->assertSame([$warning], $result->warnings());
            },
            function ($error) {
            }
        );
        $this->assertInstanceOf(FulfilledPromise::class, $result);
    }

    public function testWarnAcceptsOptions()
    {
        $warner = new ClosurePlugin(
            function ($css, $result) {
                $css->first->warn($result, 'FIRST!', ['index' => 1]);
            },
            'warner'
        );
        $result = (new Processor([$warner]))->process('a{}');
        $this->assertSame(1, count($result->warnings()), 1);
        $this->assertSame(1, $result->warnings()[0]->index);
    }

    public function testRemoveRemovesNodeFromParent()
    {
        $rule = new Rule(['selector' => 'a']);
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);
        $rule->append($decl);

        $decl->remove();
        $this->assertSame(0, count($rule->nodes));
        $this->assertNull($decl->parent);
    }

    public function testReplaceWithInsertsNewNode()
    {
        $rule = new Rule(['selector' => 'a']);
        $rule->append(['prop' => 'color', 'value' => 'black']);
        $rule->append(['prop' => 'width', 'value' => '1px']);
        $rule->append(['prop' => 'height', 'value' => '1px']);

        $node = new Declaration(['prop' => 'min-width', 'value' => '1px']);
        $width = $rule->nodes[1];
        $result = $width->replaceWith($node);

        $this->assertSame($width, $result);
        $this->assertSame("a {\n    color: black;\n    min-width: 1px;\n    height: 1px\n}", (string) $rule);
    }

    public function testReplaceWithInsertsNewRoot()
    {
        $root = new Root();
        $root->append(new AtRule(['name' => 'import', 'params' => '"a.css"']));

        $a = new Root();
        $a->append(new Rule(['selector' => 'a']));
        $a->append(new Rule(['selector' => 'b']));

        $root->first->replaceWith($a);
        $this->assertSame("a {}\nb {}", (string) $root);
    }

    public function testReplaceWithReplacesNode()
    {
        $css = Parser::parse('a{one:1;two:2}');
        $decl = ['prop' => 'fix', 'value' => 'fixed'];
        $result = $css->first->first->replaceWith($decl);

        $this->assertSame($result->prop, 'one');
        $this->assertNull($result->parent);
        $this->assertSame('a{fix:fixed;two:2}', (string) $css);
    }

    public function testToStringAcceptsCustomStringifier()
    {
        $this->assertSame(
            'a',
            (new Rule(['selector' => 'a']))->toString([self::class, 'stringify'])
        );
    }

    public function testToStringAcceptsCustomSyntax()
    {
        $this->assertSame(
            'a',
            (new Rule(['selector' => 'a']))->toString(['stringify' => [self::class, 'stringify']])
        );
    }

    public function testCloneClonesNodes()
    {
        $rule = new Rule(['selector' => 'a']);
        $rule->append(['prop' => 'color', 'value' => '/**/black']);

        $clone = clone $rule;

        $this->assertNull($clone->parent);

        $this->assertSame($rule, $rule->first->parent);
        $this->assertSame($clone, $clone->first->parent);

        $clone->append(['prop' => 'z-index', 'value' => '1']);
        $this->assertSame(1, count($rule->nodes));
    }

    public function testCloneOverridesProperties()
    {
        $rule = new Rule(['selector' => 'a']);
        $clone = $rule->createClone(['selector' => 'b']);
        $this->assertSame('b', $clone->selector);
    }

    public function testCloneCleansCodeStyle()
    {
        $css = Parser::parse('@page 1{a{color:black;}}');
        $this->assertSame(
            "@page 1 {\n    a {\n        color: black\n    }\n}",
            (string) (clone $css)
        );
    }

    public function testCloneWorksWithNullInRaws()
    {
        $decl = new Declaration([
            'prop' => 'color',
            'value' => 'black',
            'raws' => ['value' => null],
        ]);
        $clone = clone $decl;
        $this->assertSame(['value'], array_keys(get_object_vars($clone->raws)));
    }

    public function testCloneBeforeClonesAndInsertBeforeCurrentNode()
    {
        $rule = new Rule(['selector' => 'a', 'raws' => ['after' => '']]);
        $rule->append(['prop' => 'z-index', 'value' => '1', 'raws' => ['before' => '']]);

        $result = $rule->first->cloneBefore(['value' => '2']);

        $this->assertSame($rule->first, $result);
        $this->assertSame('a {z-index: 2;z-index: 1}', (string) $rule);
    }

    public function testCloneAfterClonesAndInsertAfterCurrentNode()
    {
        $rule = new Rule(['selector' => 'a', 'raws' => ['after' => '']]);
        $rule->append(['prop' => 'z-index', 'value' => '1', 'raws' => ['before' => '']]);

        $result = $rule->first->cloneAfter(['value' => '2']);

        $this->assertSame($rule->last, $result);
        $this->assertSame('a {z-index: 1;z-index: 2}', (string) $rule);
    }

    public function testNextReturnsNextNode()
    {
        $css = Parser::parse('a{one:1;two:2}');
        $this->assertSame($css->first->last, $css->first->first->next());
        $this->assertNull($css->first->last->next());
    }

    public function testPrevReturnsPreviousNode()
    {
        $css = Parser::parse('a{one:1;two:2}');
        $this->assertSame($css->first->first, $css->first->last->prev());
        $this->assertNull($css->first->first->prev());
    }

    public function testMoveToMovesNodeBetweenRoots()
    {
        $css1 = Parser::parse('a{one:1}b{two:2}');
        $css2 = Parser::parse("c {\n thr: 3\n}");
        $css1->first->moveTo($css2);

        $this->assertSame('b{two:2}', (string) $css1);
        $this->assertSame("c {\n thr: 3\n}\na {\n one: 1\n}", (string) $css2);
    }

    public function testMoveToMovesNodeInsideOneRoot()
    {
        $css = Parser::parse("a{\n one:1}\n@page {\n b {\n  two: 2\n }\n}");
        $css->first->moveTo($css->last);

        $this->assertSame("@page {\n b {\n  two: 2\n }\n a{\n  one:1\n }\n}", (string) $css);
    }

    public function testMoveBeforeMovesNodeBetweenRoots()
    {
        $css1 = Parser::parse('a{one:1}b{two:2}');
        $css2 = Parser::parse("c {\n thr: 3\n}");
        $css1->first->moveBefore($css2->first);

        $this->assertSame('b{two:2}', (string) $css1);
        $this->assertSame("a {\n one: 1\n}\nc {\n thr: 3\n}", (string) $css2);
    }

    public function testMoveBeforeMovesNodeInsideOneRoot()
    {
        $css = Parser::parse("a{\n one:1}\n@page {\n b {\n  two: 2\n }\n}");
        $css->first->moveBefore($css->last->first);

        $this->assertSame("@page {\n a{\n  one:1\n }\n b {\n  two: 2\n }\n}", (string) $css);
    }

    public function testMoveAfterMovesNodeBetweenRoots()
    {
        $css1 = Parser::parse('a{one:1}b{two:2}');
        $css2 = Parser::parse("c {\n thr: 3\n}");
        $css1->first->moveAfter($css2->first);

        $this->assertSame('b{two:2}', (string) $css1);
        $this->assertSame("c {\n thr: 3\n}\na {\n one: 1\n}", (string) $css2);
    }

    public function testMoveAfterMovesNodeInsideOneRoot()
    {
        $css = Parser::parse("a{\n one:1}\n@page {\n b {\n  two: 2\n }\n}");
        $css->first->moveAfter($css->last->first);

        $this->assertSame("@page {\n b {\n  two: 2\n }\n a{\n  one:1\n }\n}", (string) $css);
    }

    public function testToJSONCleansParentsInside()
    {
        $rule = new Rule(['selector' => 'a']);
        $rule->append(['prop' => 'color', 'value' => 'b']);

        $json = $rule->toJSON();
        $this->assertArrayNotHasKey('parent', $json);
        $this->assertArrayNotHasKey('parent', $json['nodes'][0]);

        $this->assertSame('{"selector":"a","nodes":[{"prop":"color","value":"b","type":"decl"}],"type":"rule"}', json_encode($rule->toJSON()));
    }

    public function testToJSONConvertsCustomProperties()
    {
        $root = new Root();
        $root->_cache = [1];
        $root->_hack = new \stdClass();
        $root->_hack->toJSON = function () {
            return 'hack';
        };
        $expected = [
            'type' => 'root',
            'nodes' => [],
            '_hack' => 'hack',
            '_cache' => [1],
        ];
        $calculated = $root->toJSON();
        ksort($expected);
        ksort($calculated);
        $this->assertSame($expected, $calculated);
    }

    public function testRawHasShortcutToStringifier()
    {
        $rule = new Rule(['selector' => 'a']);
        $this->assertSame('', $rule->raw('before'));
    }

    public function testRootReturnsRoot()
    {
        $css = Parser::parse('@page{a{color:black}}');
        $this->assertSame($css, $css->first->first->first->root());
    }

    public function testRootReturnsParentOfParents()
    {
        $rule = new Rule(['selector' => 'a']);
        $rule->append(['prop' => 'color', 'value' => 'black']);
        $this->assertSame($rule, $rule->first->root());
    }

    public function testRootReturnsSelfOnRoot()
    {
        $rule = new Rule(['selector' => 'a']);
        $this->assertSame($rule, $rule->root());
    }

    public function testCleanRawsCleansStyleRecursively()
    {
        $css = Parser::parse('@page{a{color:black}}');
        $css->cleanRaws();

        $this->assertSame("@page {\n    a {\n        color: black\n    }\n}", (string) $css);
        $this->assertNull($css->first->raws->before);
        $this->assertNull($css->first->first->first->raws->before);
        $this->assertNull($css->first->raws->between);
        $this->assertNull($css->first->first->first->raws->between);
        $this->assertNull($css->first->raws->after);
    }

    public function testCleanRawsKeepsBetweenOnRequest()
    {
        $css = Parser::parse('@page{a{color:black}}');
        $css->cleanRaws(true);

        $this->assertSame("@page{\n    a{\n        color:black\n    }\n}", (string) $css);
        $this->assertInternalType('string', $css->first->raws->between);
        $this->assertInternalType('string', $css->first->first->first->raws->between);
        $this->assertNull($css->first->raws->before);
        $this->assertNull($css->first->first->first->raws->before);
        $this->assertNull($css->first->raws->after);
    }

    public function testPositionInsideReturnsPositionWhenNodeStartsMidLine()
    {
        $css = Parser::parse('a {  one: X  }');
        $one = $css->first->first;
        $this->assertSame($one->positionInside(6), ['line' => 1, 'column' => 12]);
    }

    public function testPositionInsideReturnsPositionWhenBeforeContainsNewline()
    {
        $css = Parser::parse("a {\n  one: X}");
        $one = $css->first->first;
        $this->assertSame($one->positionInside(6), ['line' => 2, 'column' => 9]);
    }

    public function testPositionInsideReturnsPositionWhenNodeContainsNewlines()
    {
        $css = Parser::parse("a {\n\tone: 1\n\t\tX\n3}");
        $one = $css->first->first;
        $this->assertSame($one->positionInside(10), ['line' => 3, 'column' => 4]);
    }
}
