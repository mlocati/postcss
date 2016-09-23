<?php

spl_autoload_register(
    function ($class) {
        if (strpos($class, 'PostCSS\\') !== 0) {
            return;
        }
        $file = __DIR__.DIRECTORY_SEPARATOR.'src'.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('PostCSS'))).'.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);

require_once __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
