<?php

namespace PostCSS\Tests;

use Exception;
use PostCSS\Parser;

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    public function providerTestReal()
    {
        return [
            ['GitHub', 'https://github.com/'],
            ['Twitter', 'https://twitter.com/'],
            ['Bootstrap', 'github:twbs/bootstrap:dist/css/bootstrap.css'],
            ['Habrahabr', 'https://habrahabr.ru/'],
        ];
    }

    private static function getRemoteContents($url, $parentUrl = null)
    {
        if (strpos($url, 'github:') === 0) {
            $p = explode(':', $url);
            $url = 'https://raw.githubusercontent.com/'.$p[1].'/master/'.$p[2];
        }
        $headers = @get_headers($url);
        if (empty($headers)) {
            throw new Exception("Failed to get headers of $url");
        }
        if (!preg_match('/^\w+\/\d+(?:\.\d+)*\s+(\d+)\s+(.+)$/', $headers[0], $m)) {
            throw new Exception("Failed to parse header {$headers[0]} of $url");
        }
        $code = (int) $m[1];
        if ($code < 200 || $code >= 400) {
            throw new Exception("Error retrieving $url: $code ({$m[2]})");
        }
        $contentEncoding = '';
        foreach ($headers as $header) {
            if (preg_match('/^Content-Encoding:\s*(.+?)\s*/i', $header, $m)) {
                $contentEncoding = trim($m[1]);
            }
        }
        switch ($contentEncoding) {
            case '':
                break;
            default:
                throw new Exception("Unable to handle content encoding $contentEncoding for $url");
        }
        $data = @file_get_contents($url);
        if ($data === false) {
            throw new Exception("Failed to retrieve contents of $url");
        }
        switch ($contentEncoding) {
        }

        return $data;
    }

    /**
     * @dataProvider providerTestReal
     */
    public function testReal($name, $url)
    {
        $resourceUrls = [];
        if (substr($url, -4) === '.css') {
            $resourceUrls[$url] = self::getRemoteContents($url);
        } else {
            $contents = self::getRemoteContents($url);
            if (!preg_match_all('/[^"]+\.css"|[^\']+\.css\'/', $contents, $m)) {
                throw new Exception("Can't find CSS links at $url");
            }
            $files = array_map(
                function ($path) use ($url) {
                    $path = substr($path, 0, -1);
                    if (preg_match('/^https?:/', $path)) {
                        return $path;
                    }
                    if (strpos($path, '//') === 0) {
                        return 'http:'.$path;
                    }

                    return preg_replace('/^\.?\.?\/?/', $site, $url);
                },
                $m[0]
            );
            foreach ($files as $file) {
                $resourceUrls[$file] = self::getRemoteContents($file);
            }
        }

        foreach ($resourceUrls as $resourceUrl => $resourceData) {
            $x = Parser::parse($resourceData)->toResult(['map' => ['annotation' => false]]);
            $this->assertSame($resourceData, $x->css);
        }
    }
}
