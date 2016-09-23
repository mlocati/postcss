<?php

namespace PostCSS\Tests;

use PostCSS\Exception\CssSyntaxError;
use PostCSS\Parser;
use PostCSS\Plugin\ClosurePlugin;
use PostCSS\Processor;
use PostCSS\Node;
use PostCSS\Root;
use PostCSS\Path\NodeJS as Path;
use PostCSS\LazyResult;
use PostCSS\Result;

class ProcessorTest extends \PHPUnit_Framework_TestCase
{
    public static function prs()
    {
        return new Root(['raws' => ['after' => 'ok']]);
    }

    public static function str(Node $node, callable $builder)
    {
        $builder($node->raws->after.'!');
    }

    private static $beforeFix = null;
    protected static function getBeforeFix()
    {
        if (self::$beforeFix === null) {
            self::$beforeFix = new Processor([new ClosurePlugin(
                function ($css) {
                    $css->walkRules(function ($rule) {
                        if (!preg_match('/::(before|after)/', $rule->selector)) {
                            return;
                        }
                        if (!$rule->some(function ($i) {
                            return $i->prop === 'content';
                        })) {
                            $rule->prepend(['prop' => 'content', 'value' => '""']);
                        }
                    });
                }
            )]);
        }

        return self::$beforeFix;
    }
/*
test.before( function() {
    sinon.stub(console, 'warn');
});

test.after( function() {
    console.warn.restore();
});
*/
    public function testAddsNewPlugins()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $processor = new Processor();
        $processor->usePlugin($a);
        $this->assertSame([$a], $processor->plugins);
    }

    public function testAddsNewPluginByObject()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $processor = new Processor();
        $processor->usePlugin(['postcss' => $a]);
        $this->assertSame([$a], $processor->plugins);

        $o = new \stdClass();
        $o->postcss = $a;
        $processor2 = new Processor();
        $processor2->usePlugin($o);
        $this->assertSame([$a], $processor->plugins);
    }

    public function testAddsNewPluginByObjectFunction()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $obj = new ClosurePlugin(function () {
            return 2;
        });
        $obj->postcss = $a;
        $processor = new Processor();
        $processor->usePlugin($obj);
        $this->assertSame([$a], $processor->plugins);
    }

    public function testAddsNewProcessorsOfAnotherPostcssInstance()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $processor = new Processor();
        $other = new Processor([$a]);
        $processor->usePlugin($other);
        $this->assertSame([$a], $processor->plugins);
    }

    public function testAddsNewProcessorsFromObject()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $processor = new Processor();
        $other = new Processor([$a]);
        $processor->usePlugin(['postcss' => $other]);
        $this->assertSame([$a], $processor->plugins);
    }

    public function testReturnsItself()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $b = new ClosurePlugin(function () {
            return 2;
        });
        $processor = new Processor();
        $this->assertSame([$a, $b], $processor->usePlugin($a)->usePlugin($b)->plugins);
    }

    public function testThrowsOnWrongFormat()
    {
        $pr = new Processor();
        $err = null;
        try {
            $pr->usePlugin(1);
        } catch (\Exception $x) {
            $err = $x;
        }
        $this->assertInstanceOf(\Exception::class, $err);
        $this->assertContains('1 is not a PostCSS plugin', $err->getMessage());
    }

    public function testProcessesCSS()
    {
        $result = static::getBeforeFix()->process('a::before{top:0}');
        $this->assertSame('a::before{content:"";top:0}', $result->css);
    }

    public function testProcessesParsedAST()
    {
        $root = Parser::parse('a::before{top:0}');
        $result = static::getBeforeFix()->process($root);
        $this->assertSame('a::before{content:"";top:0}', $result->css);
    }

    public function testProcessesPreviousResult()
    {
        $result = (new Processor())->process('a::before{top:0}');
        $result = static::getBeforeFix()->process($result);
        $this->assertSame('a::before{content:"";top:0}', $result->css);
    }

    public function testTakesMapsFromPreviousResult()
    {
        $one = (new Processor())->process('a{}', [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => false],
        ]);
        $two = (new Processor())->process($one, ['to' => 'c.css']);
        $this->assertSame(['a.css'], $two->map->toJSON()['sources']);
    }

    public function testInlinesMapsFromPreviousResult()
    {
        $one = (new Processor())->process(
            'a{}',
            [
                'from' => 'a.css',
                'to' => 'b.css',
                'map' => ['inline' => false],
            ]
        );
        $two = (new Processor())->process(
            $one,
            [
                'to' => 'c.css',
                'map' => ['inline' => true],
            ]
        );
        $this->assertNull($two->map);
    }

    public function testThrowsWithFileName()
    {
        $error = null;
        try {
            (new Processor())->process('a {', ['from' => 'a.css'])->css;
        } catch (CssSyntaxError $x) {
            $error = $x;
        }
        $this->assertInstanceOf(CssSyntaxError::class, $error);
        $this->assertSame(Path::resolve('a.css'), $error->getPostCSSFile());
        $this->assertRegExp('/a.css:1:1: Unclosed block$/', $error->getMessage());
    }

    public function testAllowsToReplaceRoot()
    {
        $plugin = new ClosurePlugin(function ($css, $result) {
            $result->root = new Root();
        });
        $processor = new Processor([$plugin]);
        $this->assertSame('', $processor->process('a {}')->css);
    }

    public function testReturnsLazyResultObject()
    {
        $result = (new Processor())->process('a{}');
        $this->assertInstanceOf(LazyResult::class, $result);
        $this->assertSame('a{}', $result->css);
        $this->assertSame('a{}', (string) $result);
    }

    public function testCallsAllPluginsOnce()
    {
        $calls = '';
        $a = new ClosurePlugin(function () use (&$calls) {
            $calls .= 'a';
        });
        $b = new ClosurePlugin(function () use (&$calls) {
            $calls .= 'b';
        });

        $assertions = 0;
        $me = $this;
        $result = (new Processor([$a, $b]))
            ->process('')
            ->then(
                function () use ($me, &$calls, &$assertions) {
                    $me->assertSame('ab', $calls);
                    ++$assertions;
                },
                function () {
                }
            );
        $this->assertSame(1, $assertions);
    }

    public function testParsesConvertsAndStringifiesCSS()
    {
        $assertions = 0;
        $me = $this;
        $a = new ClosurePlugin(
            function ($css) use ($me, &$assertions) {
                $me->assertInstanceOf(Root::class, $css);
                ++$assertions;
            }
        );
        $this->assertInternalType('string', (new Processor([$a]))->process('a {}')->css);
        $this->assertSame(1, $assertions);
    }

    public function testSendResultToPlugins()
    {
        $processor = new Processor();
        $assertions = 0;
        $me = $this;
        $a = new ClosurePlugin(
            function ($css, $result) use ($me, &$assertions, $processor) {
                $me->assertInstanceOf(Result::class, $result);
                ++$assertions;
                $me->assertSame($processor, $result->processor);
                ++$assertions;
                $me->assertSame(['map' => true], $result->opts);
                ++$assertions;
                $me->assertSame($css, $result->root);
                ++$assertions;
            }
        );
        $processor->usePlugin($a)->process('a {}', ['map' => true])->css;
        $this->assertSame(4, $assertions);
    }

    public function testAcceptsSourceMapFromPostCSS()
    {
        $one = (new Processor())->process(
            'a{}',
            [
                'from' => 'a.css',
                'to' => 'b.css',
                'map' => ['inline' => false],
            ]
        );
        $two = (new Processor())->process(
            $one->css,
            [
                'from' => 'b.css',
                'to' => 'c.css',
                'map' => ['prev' => $one->map, 'inline' => false],
            ]
        );
        $this->assertSame(['a.css'], $two->map->toJSON()['sources']);
    }

    public function testWorksAsyncWithoutPlugins()
    {
        $me = $this;
        $assertions = 0;

        return (new Processor())->process('a {}')->then(
            function ($result) use ($me, &$assertions) {
                $me->assertSame('a {}', $result->css);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(1, $assertions);
    }

    public function testSetsLastPluginToResult()
    {
        $assertions = 0;
        $me = $this;
        $plugin1 = new ClosurePlugin(function ($css, $result) use ($me, &$assertions, &$plugin1) {
            $me->assertSame($result->lastPlugin, $plugin1);
            ++$assertions;
        });
        $plugin2 = new ClosurePlugin(function ($css, $result) use ($me, &$assertions, &$plugin2) {
            $me->assertSame($result->lastPlugin, $plugin2);
            ++$assertions;
        });

        $processor = new Processor([$plugin1, $plugin2]);
        $processor->process('a{}')->then(
            function ($result) use ($me, &$assertions, $plugin2) {
                $me->assertSame($result->lastPlugin, $plugin2);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(3, $assertions);
    }

    public function testUsesCustomParsers()
    {
        $assertions = 0;
        $me = $this;
        $processor = new Processor([]);
        $processor->process(
            'a{}',
            ['parser' => [self::class, 'prs']]
        )->then(
            function ($result) use ($me, &$assertions) {
                $me->assertSame('ok', $result->css);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(1, $assertions);
    }

    public function testUsesCustomParsersFromObject()
    {
        $me = $this;
        $assertions = 0;
        $processor = new Processor([]);
        $syntax = ['parse' => [self::class, 'prs'], 'stringify' => [self::class, 'str']];
        $processor->process(
            'a{}', ['parser' => $syntax]
        )->then(
            function ($result) use ($me, &$assertions) {
                $me->assertSame('ok', $result->css);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(1, $assertions);
    }

    public function testUsesCustomStringifier()
    {
        $me = $this;
        $assertions = 0;
        $processor = new Processor([]);
        $processor->process(
            'a{}',
            ['stringifier' => [self::class, 'str']]
        )->then(
            function ($result) use ($me, &$assertions) {
                $me->assertSame('!', $result->css);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(1, $assertions);
    }

    public function testUsesCustomStringifierFromObject()
    {
        $me = $this;
        $assertions = 0;
        $processor = new Processor([]);
        $syntax = ['parse' => [self::class, 'prs'], 'stringify' => [self::class, 'str']];
        $processor->process(
            '',
            ['stringifier' => $syntax]
        )->then(
            function ($result) use ($me, &$assertions) {
                $me->assertSame('!', $result->css);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(1, $assertions);
    }

    public function testUsesCustomStringifierWithSourceMaps()
    {
        $me = $this;
        $assertions = 0;
        $processor = new Processor([]);
        $processor->process('a{}', ['map' => true, 'stringifier' => [self::class, 'str']])
        ->then(
            function ($result) use ($me, &$assertions) {
                $me->assertRegExp('/!\n\/\*# sourceMap/', $result->css);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(1, $assertions);
    }

    public function testUsesCustomSyntax()
    {
        $me = $this;
        $assertions = 0;
        $processor = new Processor([]);
        $syntax = ['parse' => [self::class, 'prs'], 'stringify' => [self::class, 'str']];

        return $processor->process(
            'a{}',
            ['syntax' => $syntax]
        )->then(
            function ($result) use ($me, &$assertions) {
                $me->assertSame('ok!', $result->css);
                ++$assertions;
            },
            function () {
            }
        );
        $this->assertSame(1, $assertions);
    }
}
