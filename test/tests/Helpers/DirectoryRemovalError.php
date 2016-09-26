<?php

namespace PostCSS\Tests\Helpers;

class DirectoryRemovalError extends FilesystemError
{
    public function __construct($path, $code = null, $previous = null)
    {
        parent::__construct("Failed to remove directory $path", $code, $previous);
        $this->path = $path;
    }
}
