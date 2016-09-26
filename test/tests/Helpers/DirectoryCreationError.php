<?php

namespace PostCSS\Tests\Helpers;

class DirectoryCreationError extends FilesystemError
{
    public function __construct($path, $code = null, $previous = null)
    {
        parent::__construct("Failed to create directory $path", $code, $previous);
        $this->path = $path;
    }
}
