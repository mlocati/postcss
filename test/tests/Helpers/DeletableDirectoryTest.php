<?php

namespace PostCSS\Tests\Helpers;

abstract class DeletableDirectoryTest extends FilesystemTest
{
    protected function tearDown()
    {
        parent::tearDown();
        $dir = $this->getDirectoryPath();
        if (is_dir($dir)) {
            self::deleteDirectory($dir);
        }
        if (file_exists($dir)) {
            throw new DirectoryRemovalError($dir);
        }
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
}
