<?php

namespace PostCSS\Tests\Helpers;

use Exception;

abstract class DeletableDirectoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Return the name (without path) of the temporary directory to create/delete.
     *
     * @return string
     */
    abstract protected function getDirectoryName();

    protected function tearDown()
    {
        parent::tearDown();
        $dir = $this->getDirectoryPath();
        if (is_dir($dir)) {
            self::deleteDirectory($dir);
        }
        if (file_exists($dir)) {
            throw new Exception("Failed to delete directory: $dir");
        }
    }

    /**
     * Return the full path of the temporary directory to create/delete.
     *
     * @return string
     */
    protected function getDirectoryPath()
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [
                dirname(dirname(__DIR__)),
                'tmp',
                $this->getDirectoryName(),
            ]
        );
    }

    /**
     * Recusrively delete a directory.
     *
     * @param string $dir
     *
     * @return bool
     */
    private static function deleteDirectory($dir)
    {
        $result = true;
        if (is_dir($dir)) {
            $contents = @scandir($dir);
            if ($contents) {
                foreach ($contents as $c) {
                    switch ($c) {
                        case '.':
                        case '..':
                            break;
                        default:
                            $cf = $dir.DIRECTORY_SEPARATOR.$c;
                            if (is_dir($cf)) {
                                if (self::deleteDirectory($cf) === false) {
                                    $result = false;
                                    break;
                                }
                            } elseif (is_file($cf)) {
                                if (@unlink($cf) === false) {
                                    $result = false;
                                    break;
                                }
                            }
                            break;
                    }
                }
            }
            if ($result === true) {
                if (@rmdir($dir) === false) {
                    $result = false;
                }
            }
        }

        return $result;
    }

    protected function getAbsoluteFilePath($name)
    {
        return $this->getDirectoryPath().DIRECTORY_SEPARATOR.trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name), DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $name
     * @param string $contents
     *
     * @return string
     */
    protected function createRelativeFile($name, $contents)
    {
        $path = $this->getAbsoluteFilePath($name);
        $this->createAbsoluteFile($path, $contents);

        return $path;
    }

    /**
     * @param string $path
     * @param string $contents
     */
    protected function createAbsoluteFile($path, $contents)
    {
        $p = strrpos($path, DIRECTORY_SEPARATOR);
        $subDir = null;
        if ($p !== false) {
            $subDir = substr($path, 0, $p);
            if (!is_dir($subDir)) {
                @mkdir($subDir, true, 0777);
                if (!is_dir($subDir)) {
                    $this->markTestSkipped("Failed to create directory: $subDir");
                }
            }
        }
        if (@file_put_contents($path, $contents) === false) {
            if ($subDir === null) {
                $msg = "Failed to create file: $path";
            } else {
                $msg = "Failed to create file $path (detected directory: $subDir)";
            }
            $this->markTestSkipped($msg);
        }
    }
}
