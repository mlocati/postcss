<?php

namespace PostCSS\Exception;

class UnsupportedSourceMapEncoding extends Exception
{
    /**
     * @var string
     */
    protected $encoding;

    /**
     * @param string $encoding
     * @param int|null $code
     * @param \Exception|null $previous
     */
    public function __construct($encoding, $code = null, $previous = null)
    {
        $this->encoding = trim((string) $encoding);
        $message = 'Unsupported source map encoding';
        if ($this->encoding !== '') {
            $message .= ': '.$this->encoding;
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }
}
