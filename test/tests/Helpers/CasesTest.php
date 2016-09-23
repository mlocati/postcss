<?php

namespace PostCSS\Tests\Helpers;

use PostCSS\Parser;

abstract class CasesTest extends \PHPUnit_Framework_TestCase
{
    private static $parseCases = null;

    protected static function getParseCases($id = null)
    {
        if (!isset(self::$parseCases)) {
            $parseCases = [];
            $dir = dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'postcss-parser-tests';
            $contents = @scandir($dir);
            if ($contents === false) {
                throw new \Exception("Failed to read the $dir directory");
            }
            foreach ($contents as $c) {
                switch ($c) {
                    case '.':
                    case '..':
                        break;
                    default:
                        if (!preg_match('/^(.+)\.(css|json)$/', $c, $m)) {
                            throw new \Exception("Unknown entry in assets/postcss-parser-tests directory: $c");
                        }
                        $key = $m[1];
                        if (isset($parseCases[$key])) {
                            continue;
                        }
                        $parseCases[$key] = [
                            'path' => [],
                            'contents' => [],
                        ];
                        foreach (['css', 'json'] as $kind) {
                            $path = $dir.DIRECTORY_SEPARATOR.$key.'.'.$kind;
                            if (is_file($path)) {
                                $parseCases[$key]['path'][$kind] = $path;
                                $contents = @file_get_contents($parseCases[$key]['path'][$kind]);
                                if ($contents === false) {
                                    throw new \Exception("Unable to read the entry in assets/postcss-parser-tests directory: $key.$kind");
                                }
                                $parseCases[$key]['contents'][$kind] = trim($contents);
                            } else {
                                switch ($key.'@'.$kind) {
                                    case 'bom@css':
                                        $parseCases[$key]['contents'][$kind] = pack('CCC', 0xEF, 0xBB, 0xBF).'a{}';
                                        break;
                                    case 'spaces@css':
                                        $parseCases[$key]['contents'][$kind] = " \n";
                                        break;
                                    default:
                                        throw new \Exception("Unable to determine the entry in assets/postcss-parser-tests directory: $key.$kind");
                                }
                            }
                        }
                        break;
                }
            }

            self::$parseCases = $parseCases;
        }

        if ($id !== null) {
            return isset(self::$parseCases[$id]) ? self::$parseCases[$id] : null;
        }

        return self::$parseCases;
    }
}
