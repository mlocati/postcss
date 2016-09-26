<?php

namespace PostCSS\Tests;

use Exception;
use PostCSS\Tests\Helpers\DownloadError;
use PostCSS\Parser;
use PostCSS\Tests\Helpers\FilesystemError;

class IntegrationTest extends Helpers\FilesystemTest
{
    protected function getDirectoryName()
    {
        return 'cache';
    }

    public function providerTestReal()
    {
        return [
            ['GitHub', 'https://github.com/'],
            ['Twitter', 'https://twitter.com/'],
            ['Bootstrap', 'github:twbs/bootstrap:dist/css/bootstrap.css'],
            ['Habrahabr', 'https://habrahabr.ru/'],
        ];
    }

    private function getRemoteContents($url, $parentUrl = null)
    {
        if (strpos($url, 'github:') === 0) {
            $p = explode(':', $url);
            $url = 'https://raw.githubusercontent.com/'.$p[1].'/master/'.$p[2];
        }
        $cacheName = md5($url.((string) $parentUrl));
        if (preg_match('/^\w+:\/\/([\w\-]+(?:\.[\w\-]+)+)/', $url, $m)) {
            $cacheName .= '-'.$m[1];
        }
        if (preg_match('/\/([\w\-]+)((?:\.\w+)*)$/', $url, $m)) {
            $cacheName .= '-'.$m[1];
            if (isset($m[2]) && $m[2]) {
                $cacheName .= $m[2];
            } else {
                $cacheName .= '.txt';
            }
        } else {
            $cacheName .= '.txt';
        }
        $cachePath = $this->getAbsoluteFilePath($cacheName);
        if (is_file($cachePath)) {
            $result = @file_get_contents($cachePath);
            if ($result !== false) {
                return $result;
            }
        }
        $headers = @get_headers($url);
        if (empty($headers)) {
            throw new DownloadError("Failed to get headers of $url");
        }
        if (!preg_match('/^\w+\/\d+(?:\.\d+)*\s+(\d+)\s+(.+)$/', $headers[0], $m)) {
            throw new DownloadError("Failed to parse header {$headers[0]} of $url");
        }
        $code = (int) $m[1];
        if ($code < 200 || $code >= 400) {
            throw new DownloadError("Error retrieving $url: $code ({$m[2]})");
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
                throw new DownloadError("Unable to handle content encoding $contentEncoding for $url");
        }
        $data = @file_get_contents($url);
        if ($data === false) {
            throw new DownloadError("Failed to retrieve contents of $url");
        }
        switch ($contentEncoding) {
        }

        try {
            $this->createAbsoluteFile($cachePath, $data);
        } catch (FilesystemError $x) {
        }

        return $data;
    }

    /**
     * @dataProvider providerTestReal
     */
    public function testReal($name, $url)
    {
        try {
            $resourceUrls = [];
            if (substr($url, -4) === '.css') {
                $resourceUrls[$url] = $this->getRemoteContents($url);
            } else {
                $contents = $this->getRemoteContents($url);
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
                    $resourceUrls[$file] = $this->getRemoteContents($file);
                }
            }

            foreach ($resourceUrls as $resourceUrl => $resourceData) {
                $x = Parser::parse($resourceData)->toResult(['map' => ['annotation' => false]]);
                $this->assertSame($resourceData, $x->css);
            }
        } catch (DownloadError $err) {
            $this->markTestSkipped($err->getMessage());
        }
    }
}
