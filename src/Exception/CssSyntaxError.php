<?php

namespace PostCSS\Exception;

use PostCSS\Plugin\PluginInterface;
use PostCSS\Terminal;

/**
 * The CSS parser throws this error for broken CSS.
 *
 * Custom parsers can throw this error for broken custom syntax using Node->error method.
 *
 * PostCSS will use the input source map to detect the original error location.
 * If you wrote a Sass file, compiled it to CSS and then parsed it with PostCSS, PostCSS will show the original position in the Sass file.
 *
 * If you need the position in the PostCSS input (e.g., to debug the previous compiler), use `$error->input['file']`.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/css-syntax-error.es6
 */
class CssSyntaxError extends Exception
{
    /**
     * @var string
     */
    protected $postcssReason;

    /**
     * @var int|null
     */
    protected $postcssLine;

    /**
     * @var int|null
     */
    protected $postcssColumn;

    /**
     * @var string
     */
    protected $postcssSource;

    /**
     * @var string
     */
    protected $postcssFile;

    /**
     * @var PluginInterface|string|null
     */
    protected $postcssPlugin;

    /**
     * @var array|null
     */
    public $input = null;

    /**
     * @param string $reason Error message
     * @param int|null $line Source line of the error
     * @param int|null $column Source column of the error
     * @param string $source Source code of the broken file
     * @param string $file Absolute path to the broken file
     * @param PluginInterface|string|null $plugin PostCSS name, if error came from plugin
     */
    public function __construct($reason, $line = null, $column = null, $source = '', $file = '', $plugin = null)
    {
        parent::__construct('');
        $this->postcssReason = (string) $reason;
        $line = ($line || $line === 0 || $line === '0') ? (int) $line : null;
        $column = ($column || $column === 0 || $column === '0') ? (int) $column : null;
        if ($line !== null && $column !== null) {
            $this->postcssLine = $line;
            $this->postcssColumn = $column;
        } else {
            $this->postcssLine = null;
            $this->postcssColumn = null;
        }
        $this->postcssSource = (string) $source;
        $this->postcssFile = (string) $file;
        $this->postcssPlugin = $plugin;
        $this->updateMessage();
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = (string) $message;
    }

    /**
     * Updates the message.
     */
    public function updateMessage()
    {
        $plugin = ($this->postcssPlugin instanceof PluginInterface) ? $this->postcssPlugin->getName() : (string) $this->postcssPlugin;
        $message = ($plugin === '') ? '' : ($plugin.': ');
        $message .= ($this->postcssFile === '') ? '<css input>' : $this->postcssFile;
        if ($this->postcssLine !== null) {
            $message .= ':'.$this->postcssLine.':'.$this->postcssColumn;
        }
        $message .= ': '.$this->postcssReason;
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getPostCSSReason()
    {
        return $this->postcssReason;
    }

    /**
     * @return int|null
     */
    public function getPostCSSLine()
    {
        return $this->postcssLine;
    }

    /**
     * @return int|null
     */
    public function getPostCSSColumn()
    {
        return $this->postcssColumn;
    }

    /**
     * @return string
     */
    public function getPostCSSSource()
    {
        return $this->postcssSource;
    }

    /**
     * @return string
     */
    public function setPostCSSSource($source)
    {
        $this->postcssSource = (string) $source;
    }

    /**
     * @return string
     */
    public function getPostCSSFile()
    {
        return $this->postcssFile;
    }

    /**
     * @return PluginInterface|string|null
     */
    public function getPostCSSPlugin()
    {
        return $this->postcssPlugin;
    }

    /**
     * @param PluginInterface|string|null $plugin
     */
    public function setPostCSSPlugin($plugin)
    {
        $this->postcssPlugin = $plugin;
    }

    /**
     * Returns a few lines of CSS source that caused the error.
     *
     * If the CSS has an input source map without `sourceContent`, this method will return an empty string.
     *
     * @param bool $color Whether arrow will be colored red by terminal color codes
     *
     * @return string Few lines of CSS source that caused the error
     */
    public function showSourceCode($color = null)
    {
        $css = $this->postcssSource;
        if ($css === '') {
            return '';
        }
        if ($color) {
            $css = Terminal::highlight($css);
        }

        $lines = preg_split('/\r?\n/', $css);
        $start = max($this->postcssLine - 3, 0);
        $end = min($this->postcssLine + 2, count($lines));

        $maxWidth = strlen((string) $end);

        $output = [];
        $relevantLines = array_slice($lines, $start, $end - $start);
        foreach ($relevantLines as $index => $line) {
            $number = $start + 1 + $index;
            $padded = substr(' '.$number, -$maxWidth);
            $gutter = ' '.$padded.' | ';
            if ($number === $this->postcssLine) {
                $spacing = preg_replace('/\d/', ' ', $gutter).preg_replace('/[^\t]/', ' ', substr($line, 0, $this->postcssColumn - 1));
                $output[] = '>'.$gutter.$line."\n ".$spacing.'^';
            } else {
                $output[] = ' '.$gutter.$line;
            }
        }

        return implode("\n", $output);
    }

    /**
     * {@inheritdoc}
     *
     * @see Exception::__toString()
     */
    public function __toString()
    {
        $fqn = get_class($this);
        $fqnParts = explode('\\', $fqn);
        if (count($fqnParts) === 3 && $fqnParts[0] === 'PostCSS' && $fqnParts[1] === 'Exception') {
            $fqn = $fqnParts[2];
        }
        $result = $fqn.': '.$this->message;
        $code = $this->showSourceCode();
        if ($code !== '') {
            $result .= "\n\n".$code."\n";
        }

        return $result;
    }
}
