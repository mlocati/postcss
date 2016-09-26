<?php

namespace PostCSS\Tests\Helpers;

class FileCreationError extends FilesystemError
{
    public function __construct($path, $code = null, $previous = null)
    {
        parent::__construct("Failed to create file $filename", $code, $previous);
        $this->path = $path;
    }
}
