<?php

namespace PostCSS\Exception;

class UnableToLoadPreviousSourceMap extends Exception
{
    /**
     * @var string
     */
    protected $sourceMapLocation;

    /**
     * @param string $sourceMapLocation
     * @param int|null $code
     * @param \Exception|null $previous
     */
    public function __construct($sourceMapLocation, $code = null, $previous = null)
    {
        $this->sourceMapLocation = trim((string) $sourceMapLocation);
        $message = 'Unable to load previous source map';
        if ($this->sourceMapLocation !== '') {
            $message .= ': '.$this->sourceMapLocation;
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getSourceMapLocation()
    {
        return $this->sourceMapLocation;
    }
}
