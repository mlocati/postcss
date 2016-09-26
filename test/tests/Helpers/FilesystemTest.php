<?php

namespace PostCSS\Tests\Helpers;

abstract class FilesystemTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Return the name (without path) of the temporary directory to create/delete.
     *
     * @return string
     */
    abstract protected function getDirectoryName();

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

    protected function getAbsoluteFilePath($name)
    {
        return $this->getDirectoryPath().DIRECTORY_SEPARATOR.trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name), DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $name
     * @param string $contents
     *
     * @throws FilesystemError
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
     *
     * @throws FilesystemError
     */
    protected function createAbsoluteFile($path, $contents)
    {
        $p = strrpos($path, DIRECTORY_SEPARATOR);
        if ($p !== false) {
            $subDir = substr($path, 0, $p);
            if (!is_dir($subDir)) {
                @mkdir($subDir, 0777, true);
                if (!is_dir($subDir)) {
                    throw new DirectoryCreationError($subDir);
                }
            }
        }
        if (@file_put_contents($path, $contents) === false) {
            throw new FileCreationError($path);
        }
    }
}
