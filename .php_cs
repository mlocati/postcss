<?php

return Symfony\CS\Config\Config::create()
    ->fixers(array(
        // Don't touch class/file name
        '-psr0',
        // Don't vertically align phpdoc tags
        '-phpdoc_params',
        // Concatenation without spaces
        'concat_without_spaces',
        // Convert double quotes to single quotes for simple strings.
        'single_quote',
        // Allow 'return null'
        '-empty_return',
        // Use the short array syntax
        'short_array_syntax',
        // Ordering use statements
        'ordered_use'
    ))
    ->finder(
        Symfony\CS\Finder\DefaultFinder::create()
            ->in(__DIR__)
    )
;
