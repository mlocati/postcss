<?php

namespace PostCSS\Exception;

class UnsupportedPreviousSourceMapFormat extends Exception
{
    /**
     * @var string
     */
    protected $format;

    /**
     * @param string $format
     * @param int|null $code
     * @param \Exception|null $previous
     */
    public function __construct($format, $code = null, $previous = null)
    {
        $this->format = trim((string) $format);
        $message = 'Unsupported previous source map format';
        if ($this->format !== '') {
            $message .= ': '.$this->format;
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }
}
