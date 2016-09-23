<?php

namespace PostCSS\Tests;

use PostCSS\Parser;
use PostCSS\SourceMap\Consumer\Consumer;

class PreviousMapTest extends Helpers\DeletableDirectoryTest
{
    protected function getDirectoryName()
    {
        return 'prevmap-fixtures';
    }

    protected static function getMapOpts()
    {
        return [
            'version' => 3,
            'file' => null,
            'sources' => [],
            'names' => [],
            'mappings' => '',
        ];
    }

    protected static function getMap()
    {
        return json_encode(static::getMapOpts());
    }

    public function testMissesPropertyIfNoMap()
    {
        $this->assertNull(Parser::parse('a{}')->source['input']->map);
    }

    public function testCreatesPropertyIfMapPresent()
    {
        $root = Parser::parse('a{}', ['map' => ['prev' => static::getMap()]]);
        $this->assertSame(static::getMap(), $root->source['input']->map->text);
    }

    public function testReturnsConsumer()
    {
        $obj = Parser::parse('a{}', ['map' => ['prev' => static::getMap()]])->source['input']->map->consumer();
        $this->assertInstanceOf(Consumer::class, $obj);
    }

    public function testSetsAnnotationProperty()
    {
        $mapOpts = ['map' => ['prev' => static::getMap()]];

        $root1 = Parser::parse('a{}', $mapOpts);
        $this->assertSame('', $root1->source['input']->map->annotation);

        $root2 = Parser::parse('a{}/*# sourceMappingURL=a.css.map */', $mapOpts);
        $this->assertSame('a.css.map', $root2->source['input']->map->annotation);
    }

    public function testChecksPreviousSourcesContent()
    {
        $map2 = [
            'version' => 3,
            'file' => 'b',
            'sources' => ['a'],
            'names' => [],
            'mappings' => '',
        ];

        $opts = ['map' => ['prev' => $map2]];
        $this->assertFalse(Parser::parse('a{}', $opts)->source['input']->map->withContent());

        $opts = ['map' => ['prev' => $map2 + ['sourcesContent' => ['a{}']]]];
        $this->assertTrue(Parser::parse('a{}', $opts)->source['input']->map->withContent());
    }

    public function testDecodesBase64Maps()
    {
        $b64 = base64_encode(static::getMap());
        $css = "a{}\n/*# sourceMappingURL=data:application/json;base64,$b64 */";

        $this->assertSame(static::getMap(), Parser::parse($css)->source['input']->map->text);
    }

    public function testDecodesBase64UTF8Maps()
    {
        $b64 = base64_encode(static::getMap());
        $css = "a{}\n/*# sourceMappingURL=data:application/json;charset=utf-8;base64,$b64 */";

        $this->assertSame(static::getMap(), Parser::parse($css)->source['input']->map->text);
    }

    public function testAcceptsDifferentNameForUTF8Encoding()
    {
        $b64 = base64_encode(static::getMap());
        $css = "a{}\n/*# sourceMappingURL=data:application/json;charset=utf8;base64,$b64 */";

        $this->assertSame(static::getMap(), Parser::parse($css)->source['input']->map->text);
    }

    public function testDecodesURIMaps()
    {
        $uri = 'data:application/json,'.urldecode(static::getMap());
        $css = "a{}\n/*# sourceMappingURL=$uri */";

        $this->assertSame(static::getMap(), Parser::parse($css)->source['input']->map->text);
    }

    public function testRemovesMapOnRequest()
    {
        $uri = 'data:application/json,'.urldecode(static::getMap());
        $css = "a{}\n/*# sourceMappingURL=$uri */";

        $input = Parser::parse($css, ['map' => ['prev' => false]])->source['input'];
        $this->assertNull($input->map);
    }

    public function testRaisesOnUnknownInlineEncoding()
    {
        $css = "a { }\n/*# sourceMappingURL=data:application/json;md5,68b329da9893e34099c7d8ad5cb9c940*/";

        $err = null;
        try {
            Parser::parse($css);
        } catch (\PostCSS\Exception\UnsupportedSourceMapEncoding $x) {
            $err = $x;
        }
        $this->assertInstanceOf(\PostCSS\Exception\UnsupportedSourceMapEncoding::class, $err);
        $this->assertSame('md5', $err->getEncoding());
    }

    public function testRaisesOnUnknownMapFormat()
    {
        $err = null;
        try {
            Parser::parse('a{}', ['map' => ['prev' => 1]]);
        } catch (\PostCSS\Exception\UnsupportedPreviousSourceMapFormat $x) {
            $err = $x;
        }
        $this->assertInstanceOf(\PostCSS\Exception\UnsupportedPreviousSourceMapFormat::class, $err);
        $this->assertSame('1', $err->getFormat());
    }

    public function testReadsMapFromAnnotation()
    {
        $file = $this->createRelativeFile('a.map', static::getMap());
        $root = Parser::parse("a{}\n/*# sourceMappingURL=a.map */", ['from' => $file]);

        $this->assertSame(static::getMap(), $root->source['input']->map->text);
        $this->assertSame(dirname($file), $root->source['input']->map->root);
    }

    public function testSetsUniqNameForInlineMap()
    {
        $map2 = [
            'version' => 3,
            'sources' => ['a'],
            'names' => [],
            'mappings' => '',
        ];

        $opts = ['map' => ['prev' => $map2]];
        $file1 = Parser::parse('a{}', $opts)->source['input']->map->file;
        $file2 = Parser::parse('a{}', $opts)->source['input']->map->file;

        $this->assertRegExp('/^<input css \d+>$/', $file1);
        $this->assertNotEquals($file1, $file2);
    }

    public function testShouldAcceptAnEmptyMappingsString()
    {
        $emptyMap = [
            'version' => 3,
            'sources' => [],
            'names' => [],
            'mappings' => '',
        ];
        Parser::parse('body{}', ['map' => ['prev' => $emptyMap]]);
    }

    public function testShouldAcceptAFunction()
    {
        $css = "body{}\n/*# sourceMappingURL=a.map */";
        $file = $this->createRelativeFile('previous-sourcemap-function.map', static::getMap());
        $opts = [
            'map' => [
                'prev' => function (/* from */) use ($file) {
                    return $file;
                },
            ],
        ];
        $root = Parser::parse($css, $opts);
        $this->assertSame(static::getMap(), $root->source['input']->map->text);
        $this->assertSame('a.map', $root->source['input']->map->annotation);
    }

    public function testShouldCallFunctionWithOptsFrom()
    {
        $numberOfAssertions = 0;

        $css = "body{}\n/*# sourceMappingURL=a.map */";
        $file = $this->createRelativeFile('previous-sourcemap-function.map', static::getMap());
        $me = $this;
        $opts = [
            'from' => 'a.css',
            'map' => [
                'prev' => function ($from) use ($me, $file, &$numberOfAssertions) {
                    $me->assertSame('a.css', $from);
                    ++$numberOfAssertions;

                    return $file;
                },
            ],
        ];
        Parser::parse($css, $opts);
        $this->assertSame(1, $numberOfAssertions);
    }

    public function testShouldRaiseWhenFunctionReturnsInvalidPath()
    {
        $css = "body{}\n/*# sourceMappingURL=a.map */";
        $fakeMap = ((string) PHP_INT_MAX).'.map';
        $fakePath = $this->getAbsoluteFilePath($fakeMap);
        $opts = [
            'map' => [
                'prev' => function () use ($fakePath) {
                    return $fakePath;
                },
            ],
        ];
        try {
            Parser::parse($css, $opts);
        } catch (\PostCSS\Exception\UnableToLoadPreviousSourceMap $x) {
            $err = $x;
        }
        $this->assertInstanceOf(\PostCSS\Exception\UnableToLoadPreviousSourceMap::class, $err);
        $this->assertSame($fakePath, $err->getSourceMapLocation());
    }
}
