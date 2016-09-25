<?php

namespace PostCSS\Tests;

use PostCSS\Processor;
use PostCSS\Plugin\ClosurePlugin;
use PostCSS\Parser;
use PostCSS\Root;
use PostCSS\Comment;
use PostCSS\AtRule;
use PostCSS\Rule;
use PostCSS\Declaration;
use PostCSS\Plugin\ConfigurableClosurePlugin;
use PostCSS\Vendor;

class PostCSSTest extends \PHPUnit_Framework_TestCase
{
    public function testCreatesPluginsList()
    {
        $processor = new Processor();
        $this->assertInstanceOf(Processor::class, $processor);
        $this->assertEmpty($processor->plugins);
    }

    public function testSavesPluginsList()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $b = new ClosurePlugin(function () {
            return 2;
        });
        $this->assertSame([$a, $b], (new Processor($a, $b))->plugins);
    }

    public function testSavesPluginsListAsArray()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $b = new ClosurePlugin(function () {
            return 2;
        });
        $this->assertSame([$a, $b], (new Processor([$a, $b]))->plugins);
    }

    public function testTakesPluginFromOtherProcessor()
    {
        $a = new ClosurePlugin(function () {
            return 1;
        });
        $b = new ClosurePlugin(function () {
            return 2;
        });
        $c = new ClosurePlugin(function () {
            return 3;
        });
        $other = new Processor([$a, $b]);
        $this->assertSame([$a, $b, $c], (new Processor([$other, $c]))->plugins);
    }

    public function testSupportsInjectingAdditionalProcessorsAtRuntime()
    {
        $plugin1 = new ClosurePlugin(
            function ($css) {
                $css->walkDecls(function ($decl) {
                    $decl->value = 'world';
                });
            },
            'one'
        );
        $plugin2 = new ClosurePlugin(
            function ($css, $result) use ($plugin1) {
                $result->processor->usePlugin($plugin1);
            },
            'two'
        );
        $css = null;
        $css = (new Processor([$plugin2]))
            ->process('a{hello: bob}')
            ->css;
        $this->assertSame('a{hello: world}', $css);
    }

    public function testCreatesPlugin()
    {
        $plugin = new ConfigurableClosurePlugin(
            function ($filter = false) {
                return function ($css) use ($filter) {
                    $css->walkDecls($filter ?: 'two', function ($i) {
                        $i->remove();
                    });
                };
            },
            'test'
        );

        $func1 = (new Processor($plugin))->plugins[0];
        $this->assertSame('test', $func1->getName());
        $this->assertSame('', $func1::POSTCSS_VERSION);

        $func2 = (new Processor($plugin()))->plugins[0];
        $this->assertSame($func1->getName(), $func2->getName());
        $this->assertSame($func1::POSTCSS_VERSION, $func2::POSTCSS_VERSION);

        $result1 = (new Processor($plugin('one')))->process('a{ one: 1; two: 2 }');
        $this->assertSame('a{ two: 2 }', $result1->css);

        $result2 = (new Processor($plugin))->process('a{ one: 1; two: 2 }');
        $this->assertSame('a{ one: 1 }', $result2->css);
    }

    public function testDoesNotCallPluginConstructor()
    {
        $calls = 0;
        $plugin = new ConfigurableClosurePlugin(
            function () use (&$calls) {
                $calls += 1;

                return function () {
                };
            }
        );
        $this->assertSame(0, $calls);

        (new Processor($plugin))->process('a{}');
        $this->assertSame(1, $calls);

        (new Processor($plugin))->process('a{}');
        $this->assertSame(2, $calls);
    }

    public function testCreatesAShortcutToProcessCss()
    {
        $plugin = new ConfigurableClosurePlugin(
            function ($str = 'bar') {
                return function ($css) use ($str) {
                    $css->walkDecls(function ($i) use ($str) {
                        $i->value = $str;
                    });
                };
            }
        );

        $result1 = $plugin->process('a{value:foo}', []);
        $this->assertSame('a{value:bar}', $result1->css);

        $result2 = $plugin->process('a{value:foo}', ['baz']);
        $this->assertSame('a{value:baz}', $result2->css);

        $me = $this;
        $plugin->process('a{value:foo}', [])
            ->then(
                function ($result) use ($me) {
                    $me->assertSame('a{value:bar}', $result->css);
                },
                function () {
                }
            )
            ->done();
    }

    public function testContainsParser()
    {
        $parsed = Parser::parse('');
        $this->assertInstanceOf(Root::class, $parsed);
        $this->assertSame('root', $parsed->type);
    }

    public function testAllowsToBuildOwnCSS()
    {
        $root = new Root(['raws' => ['after' => "\n"]]);
        $comment = new Comment(['text' => 'Example']);
        $media = new AtRule(['name' => 'media', 'params' => 'screen']);
        $rule = new Rule(['selector' => 'a']);
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);

        $root->append($comment);
        $rule->append($decl);
        $media->append($rule);
        $root->append($media);

        $this->assertSame("/* Example */\n@media screen {\n    a {\n        color: black\n    }\n}\n", (string) $root);
    }

    public function testContainsVendorModule()
    {
        $this->assertSame('-moz-', Vendor::prefix('-moz-tab'));
    }

    public function testContainsListModule()
    {
        $this->assertSame(['a', 'b'], \PostCSS\ListUtil::space('a b'));
    }
}
