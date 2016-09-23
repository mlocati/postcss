<?php

namespace PostCSS\Tests;

use PostCSS\Parser;
use PostCSS\Plugin\ClosurePlugin;
use PostCSS\Processor;
use PostCSS\Result;
use PostCSS\Warning;

class ResultTest extends \PHPUnit_Framework_TestCase
{
    public function testStringifies()
    {
        $result = new Result();
        $result->css = 'a{}';
        $this->assertSame($result->css, (string) $result);
    }

    public function testAddsWarning()
    {
        $warning = null;
        $plugin = new ClosurePlugin(
            function ($css, $res) use (&$warning) {
                $warning = $res->warn('test', ['node' => $css->first]);
            },
            'test-plugin'
        );
        $result = (new Processor([$plugin]))->process('a{}')->sync();

        $this->assertEquals(
            new Warning(
                'test',
                [
                    'plugin' => 'test-plugin',
                    'node' => $result->root->first,
                ]
            ),
            $warning
        );
        $this->assertSame([$warning], $result->messages);
    }

    public function testAllowsToOverridePlugin()
    {
        $plugin = new ClosurePlugin(
            function ($css, $res) {
                $res->warn('test', ['plugin' => 'test-plugin#one']);
            },
            'test-plugin'
        );
        $result = (new Processor([$plugin]))->process('a{}')->sync();

        $this->assertSame('test-plugin#one', $result->messages[0]->plugin);
    }

    public function testAllowsRoot()
    {
        $result = new Result();
        $root = Parser::parse('a{}');
        $result->warn('TT', ['node' => $root]);

        $this->assertSame('<css input>:1:1: TT', (string) $result->messages[0]);
    }

    public function testReturnsOnlyWarnings()
    {
        $result = new Result();
        $result->messages = [
            new Warning('a'),
            new \stdClass(),
            new Warning('b'),
        ];
        $this->assertEquals(
            [
                new Warning('a'),
                new Warning('b'),
            ],
            $result->warnings()
        );
    }
}
