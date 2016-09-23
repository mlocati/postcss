<?php

namespace PostCSS\Tests;

use PostCSS\PreviousMap;
use PostCSS\SourceMap\Consumer\Consumer;
use PostCSS\SourceMap\Generator;
use PostCSS\Processor;
use PostCSS\Path\NodeJS as Path;
use PostCSS\Parser;
use PostCSS\Root;
use PostCSS\Plugin\ClosurePlugin;

class MapTest extends Helpers\DeletableDirectoryTest
{
    protected function getDirectoryName()
    {
        return 'map-fixtures';
    }

    protected static function consumer($map)
    {
        return Consumer::fromSourceMap($map);
    }

    protected static function read($result)
    {
        $css = $result->css;
        $prev = new PreviousMap($css, []);

        return $prev->consumer();
    }

    protected static function doubler()
    {
        return new Processor([
            new ClosurePlugin(
                function ($css) {
                    return $css->walkDecls(function ($decl) {
                        $decl->parent->prepend($decl->createClone());
                    });
                }
            ),
        ]);
    }

    protected static function lighter()
    {
        return new Processor([
            new ClosurePlugin(
                function ($css) {
                    return $css->walkDecls(function ($decl) {
                        $decl->value = 'white';
                    });
                }
            ),
        ]);
    }

    public function testAddsMapFieldOnlyOnRequest()
    {
        $this->assertNull((new Processor())->process('a {}')->map);
    }

    public function testReturnMapGenerator()
    {
        $map = (new Processor())->process('a {}', ['map' => ['inline' => false]])->map;
        $this->assertInstanceOf(Generator::class, $map);
    }

    public function testGenerateRightSourceMap()
    {
        $css = "a {\n  color: black;\n  }";
        $processor = new Processor([new ClosurePlugin(function ($root) {
            $root->walkRules(function ($rule) {
                $rule->selector = 'strong';
            });
            $root->walkDecls(function ($decl) {
                $decl->parent->prepend($decl->createClone(['prop' => 'background']));
            });
        })]);
        $result = $processor->process(
            $css,
            [
                'from' => 'a.css',
                'to' => 'b.css',
                'map' => true,
            ]
        );
        $map = static::read($result);
        $this->assertSame('b.css', $map->file);
        $this->assertSame(
            [
                'source' => 'a.css',
                'line' => 1,
                'column' => 0,
                'name' => '',
            ],
            $map->originalPositionFor(['line' => 1, 'column' => 0])
        );
        $this->assertSame(
            [
                'source' => 'a.css',
                'line' => 2,
                'column' => 2,
                'name' => '',
            ],
            $map->originalPositionFor(['line' => 2, 'column' => 2])
        );
        $this->assertSame(
            [
                'source' => 'a.css',
                'line' => 2,
                'column' => 2,
                'name' => '',
            ],
            $map->originalPositionFor(['line' => 3, 'column' => 2])
        );
    }

    public function testChangesPreviousSourceMap()
    {
        $css = 'a { color: black }';

        $doubled = static::doubler()->process($css, [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => false],
        ]);

        $lighted = static::lighter()->process(
            $doubled->css,
            [
                'from' => 'b.css',
                'to' => 'c.css',
                'map' => ['prev' => $doubled->map],
            ]
        );
        $map = static::consumer($lighted->map);
        $this->assertSame(
             [
                'source' => 'a.css',
                'line' => 1,
                'column' => 4,
                'name' => '',
             ],
             $map->originalPositionFor(['line' => 1, 'column' => 18])
         );
    }

    public function testMissesSourceMapAnnotationIfUserAsk()
    {
        $css = 'a { }';
        $result = (new Processor())->process($css, [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['annotation' => false],
        ]);

        $this->assertSame($css, $result->css);
    }

    public function testMissesSourceMapAnnotationIfPreviousMapMissedIt()
    {
        $css = 'a { }';

        $step1 = (new Processor())->process($css, [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['annotation' => false],
        ]);

        $step2 = (new Processor())->process($step1->css, [
            'from' => 'b.css',
            'to' => 'c.css',
            'map' => ['prev' => $step1->map],
        ]);

        $this->assertSame($css, $step2->css);
    }

    public function testUsesUserPathInAnnotationRelativeToOptionsTo()
    {
        $result = (new Processor())->process('a { }', [
            'from' => 'source/a.css',
            'to' => 'build/b.css',
            'map' => ['annotation' => 'maps/b.map'],
        ]);

        $this->assertSame("a { }\n/*# sourceMappingURL=maps/b.map */", $result->css);
        $map = static::consumer($result->map);

        $this->assertSame('../b.css', $map->file);
        $this->assertSame('../../source/a.css', $map->originalPositionFor(['line' => 1, 'column' => 0])['source']);
    }

    public function testGeneratesInlineMap()
    {
        $css = 'a { }';

        $inline = (new Processor())->process($css, [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => true],
        ]);

        $this->assertNull($inline->map);
        $this->assertRegExp('/# sourceMappingURL=data:/', $inline->css);

        $separated = (new Processor())->process($css, [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => false],
        ]);

        $base64 = base64_encode((string) $separated->map);
        $end = substr($inline->css, -strlen($base64) - 3);
        $this->assertSame($base64.' */', $end);
    }

    public function testGeneratesInlineMapByDefault()
    {
        $inline = (new Processor())->process('a { }', [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => true,
        ]);
        $this->assertRegExp('/# sourceMappingURL=data:/', $inline->css);
    }

    public function testGeneratesSeparatedMapIfPreviousMapWasNotInlined()
    {
        $step1 = static::doubler()->process('a { color: black }', [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => false],
        ]);
        $step2 = static::lighter()->process($step1->css, [
            'from' => 'b.css',
            'to' => 'c.css',
            'map' => ['prev' => $step1->map],
        ]);

        $this->assertSame('object', gettype($step2->map));
    }

    public function testGeneratesSeparatedMapOnAnnotationOption()
    {
        $result = (new Processor())->process('a { }', [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['annotation' => false],
        ]);

        $this->assertSame('object', gettype($result->map));
    }

    public function testAllowsChangeMapType()
    {
        $step1 = (new Processor())->process('a { }', [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => true],
        ]);

        $step2 = (new Processor())->process($step1->css, [
            'from' => 'b.css',
            'to' => 'c.css',
            'map' => ['inline' => false],
        ]);

        $this->assertSame('object', gettype($step2->map));
        $this->assertRegExp('/# sourceMappingURL=c\.css\.map/', $step2->css);
    }

    public function testMissesCheckFilesOnRequires()
    {
        $file = $this->getAbsoluteFilePath('a.css');

        $step1 = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => $file,
            'map' => true,
        ]);

        $this->createAbsoluteFile($file.'.map', $step1->map);
        $step2 = static::lighter()->process($step1->css, [
            'from' => $file,
            'to' => 'b.css',
            'map' => false,
        ]);

        $this->assertNull($step2->map);
    }

    public function testWorksInSubdirs()
    {
        $result = static::doubler()->process('a { }', [
            'from' => 'from/a.css',
            'to' => 'out/b.css',
            'map' => ['inline' => false],
        ]);

        $this->assertRegExp('/sourceMappingURL=b.css.map/', $result->css);

        $map = static::consumer($result->map);
        $this->assertSame('b.css', $map->file);
        $this->assertSame(['../from/a.css'], $map->sources);
    }

    public function testUsesMapFromSubdir()
    {
        $step1 = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => 'out/b.css',
            'map' => ['inline' => false],
        ]);

        $step2 = static::doubler()->process($step1->css, [
            'from' => 'out/b.css',
            'to' => 'out/two/c.css',
            'map' => ['prev' => $step1->map],
        ]);

        $source = static::consumer($step2->map)->originalPositionFor(['line' => 1, 'column' => 0])['source'];
        $this->assertSame('../../a.css', $source);

        $step3 = static::doubler()->process($step2->css, [
            'from' => 'c.css',
            'to' => 'd.css',
            'map' => ['prev' => $step2->map],
        ]);

        $source = static::consumer($step3->map)->originalPositionFor(['line' => 1, 'column' => 0])['source'];
        $this->assertSame('../../a.css', $source);
    }

    public function testUsesMapFromSubdirIfItInlined()
    {
        $step1 = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => 'out/b.css',
            'map' => true,
        ]);

        $step2 = static::doubler()->process($step1->css, [
            'from' => 'out/b.css',
            'to' => 'out/two/c.css',
            'map' => ['inline' => false],
        ]);

        $source = static::consumer($step2->map)->originalPositionFor(['line' => 1, 'column' => 0])['source'];
        $this->assertSame('../../a.css', $source);
    }

    public function testUsesMapFromSubdirIfItWrittenAsAFile()
    {
        $step1 = static::doubler()->process('a { }', [
            'from' => 'source/a.css',
            'to' => 'one/b.css',
            'map' => ['annotation' => 'maps/b.css.map', 'inline' => false],
        ]);

        $source = static::consumer($step1->map)->originalPositionFor(['line' => 1, 'column' => 0])['source'];
        $this->assertSame('../../source/a.css', $source);

        $file = $this->createRelativeFile('one/maps/b.css.map', $step1->map);
        $step2 = static::doubler()->process($step1->css, [
            'from' => $this->getAbsoluteFilePath('one/b.css'),
            'to' => $this->getAbsoluteFilePath('two/c.css'),
            'map' => true,
        ]);

        $source = static::consumer($step2->map)->originalPositionFor(['line' => 1, 'column' => 0])['source'];
        $this->assertSame('../source/a.css', $source);
    }

    public function testWorksWithDifferentTypesOfMaps()
    {
        $step1 = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => false],
        ]);

        $map = $step1->map;
        $maps = [$map, static::consumer($map), $map->toJSON(), (string) $map];

        foreach ($maps as $i) {
            $step2 = static::doubler()->process($step1->css, [
                'from' => 'b.css',
                'to' => 'c.css',
                'map' => ['prev' => $i],
            ]);
            $source = static::consumer($step2->map)->originalPositionFor(['line' => 1, 'column' => 0])['source'];
            $this->assertSame('a.css', $source);
        }
    }

    public function testSetsSourceContentByDefault()
    {
        $result = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => 'out/b.css',
            'map' => true,
        ]);

        $this->assertSame('a { }', static::read($result)->sourceContentFor('../a.css'));
    }

    public function testMissesSourceContentOnRequest1()
    {
        $result = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => 'out/b.css',
            'map' => ['sourcesContent' => false],
        ]);
        $this->assertNull(static::read($result)->sourceContentFor('../a.css'));
    }

    public function testMissesSourceContentIfPreviousNotHave()
    {
        $step1 = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => 'out/a.css',
            'map' => ['sourcesContent' => false],
        ]);

        $file1 = Parser::parse($step1->css, [
            'from' => 'a.css',
            'map' => ['prev' => $step1->map],
        ]);
        $file2 = Parser::parse('b { }', ['from' => 'b.css', 'map' => true]);

        $file2->append(clone $file1->first);
        $step2 = $file2->toResult(['to' => 'c.css', 'map' => true]);

        $this->assertNull(static::read($step2)->sourceContentFor('b.css'));
    }

    public function testMissesSourceContentOnRequest2()
    {
        $step1 = static::doubler()->process('a { }', [
            'from' => 'a.css',
            'to' => 'out/a.css',
            'map' => ['sourcesContent' => true],
        ]);

        $file1 = Parser::parse($step1->css, [
            'from' => 'a.css',
            'map' => ['prev' => $step1->map],
        ]);
        $file2 = Parser::parse('b { }', ['from' => 'b.css', 'map' => true]);

        $file2->append(clone $file1->first);
        $step2 = $file2->toResult([
            'to' => 'c.css',
            'map' => ['sourcesContent' => false],
        ]);

        $map = static::read($step2);
        $this->assertNull($map->sourceContentFor('b.css'));
        $this->assertNull($map->sourceContentFor('../a.css'));
    }

    public function testDetectsInputFileNameFromMap()
    {
        $one = static::doubler()->process('a { }', ['to' => 'a.css', 'map' => true]);
        $two = static::doubler()->process($one->css, ['map' => ['prev' => $one->map]]);
        $this->assertSame(Path::resolve('a.css'), $two->root->first->source['input']->file);
    }

    public function testWorksWithoutFileNames()
    {
        $step1 = static::doubler()->process('a { }', ['map' => true]);
        $step2 = static::doubler()->process($step1->css);
        $this->assertRegExp('/a \{ \}\n\/\*/', $step2->css);
    }

    public function testSupportsUTF8()
    {
        $step1 = static::doubler()->process('a { }', [
            'from' => 'вход.css',
            'to' => 'шаг1.css',
            'map' => true,
        ]);
        $step2 = static::doubler()->process($step1->css, [
            'from' => 'шаг1.css',
            'to' => 'выход.css',
        ]);

        $this->assertSame('выход.css', static::read($step2)->file);
    }

    public function testGeneratesMapForNodeCreatedManually()
    {
        $contenter = new Processor([
            new ClosurePlugin(function ($css) {
                $css->first->prepend(['prop' => 'content', 'value' => '""']);
            }),
        ]);
        $result = $contenter->process("a:after{\n}", ['map' => true]);
        $map = static::read($result);
        $expected = [
            'source' => '<no source>',
            'column' => 0,
            'line' => 1,
            'name' => '',
        ];
        $calculated = $map->originalPositionFor(['line' => 2, 'column' => 5]);
        ksort($expected);
        ksort($calculated);
        $this->assertSame($expected, $calculated);
    }

    public function testUsesInputFileNameAsOutputFileName()
    {
        $result = static::doubler()->process('a{}', [
            'from' => 'a.css',
            'map' => ['inline' => false],
        ]);
        $this->assertSame('a.css', $result->map->toJSON()['file']);
    }

    public function testUsesToDotcssAsDefaultOutputName()
    {
        $result = static::doubler()->process('a{}', ['map' => ['inline' => false]]);
        $this->assertSame('to.css', $result->map->toJSON()['file']);
    }

    public function testSupportsAnnotationCommentInAnyPlace()
    {
        $css = '/*# sourceMappingURL=a.css.map */a { }';
        $result = (new Processor())->process($css, [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['inline' => false],
        ]);

        $this->assertSame("a { }\n/*# sourceMappingURL=b.css.map */", $result->css);
    }

    public function testDoesBotUpdateAnnotationOnRequest()
    {
        $css = 'a { }/*# sourceMappingURL=a.css.map */';
        $result = (new Processor())->process($css, [
            'from' => 'a.css',
            'to' => 'b.css',
            'map' => ['annotation' => false, 'inline' => false],
        ]);

        $this->assertSame('a { }/*# sourceMappingURL=a.css.map */', $result->css);
    }

    public function testClearsSourceMap()
    {
        $css1 = (new Root())->toResult(['map' => true])->css;
        $css2 = (new Root())->toResult(['map' => true])->css;

        $root = new Root();
        $root->append($css1);
        $root->append($css2);

        $css = $root->toResult(['map' => true])->css;
        $this->assertSame(1, preg_match_all('/sourceMappingURL/', $css));
    }

    public function testUsesWindowsLineSeparationToo()
    {
        $result = (new Processor())->process("a {\r\n}", ['map' => true]);
        $this->assertRegExp('/a \{\r\n\}\r\n\/\*# sourceMappingURL=/', $result->css);
    }

    public function testMapFromShouldOverrideTheSourceMapSources()
    {
        $result = (new Processor())->process('a{}', [
            'map' => [
                'inline' => false,
                'from' => 'file:///dir/a.css',
            ],
        ]);
        $this->assertSame(['file:///dir/a.css'], $result->map->toJSON()['sources']);
    }

    public function testPreservesAbsoluteUrlsInTo()
    {
        $result = (new Processor())->process('a{}', [
            'from' => '/dir/to/a.css',
            'to' => 'http://example.com/a.css',
            'map' => ['inline' => false],
        ]);
        $this->assertSame('http://example.com/a.css', $result->map->toJSON()['file']);
    }

    public function testPreservesAbsoluteUrlsInSources()
    {
        $result = (new Processor())->process('a{}', [
            'from' => 'file:///dir/a.css',
            'to' => 'http://example.com/a.css',
            'map' => ['inline' => false],
        ]);
        $this->assertSame(['file:///dir/a.css'], $result->map->toJSON()['sources']);
    }

    public function testPreservesAbsoluteUrlsInSourcesFromPreviousMap()
    {
        $result1 = (new Processor())->process('a{}', [
            'from' => 'http://example.com/a.css',
            'to' => 'http://example.com/b.css',
            'map' => true,
        ]);
        $result2 = (new Processor())->process($result1->css, [
            'to' => 'http://example.com/c.css',
            'map' => [
                'inline' => false,
            ],
        ]);
        $this->assertSame('http://example.com/b.css', $result2->root->source['input']->file);
        $this->assertSame(['http://example.com/a.css'], $result2->map->toJSON()['sources']);
    }
}
