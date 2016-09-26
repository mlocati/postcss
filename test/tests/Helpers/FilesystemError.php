<?php

namespace PostCSS\Tests\Helpers;

abstract class FilesystemError extends \Exception
{
    protected $path;

    public function getPath()
    {
        return $this->path;
    }
}
