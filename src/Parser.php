<?php

namespace PostCSS;

/**
 * The {@link Root}, {@link AtRule}, and {@link Rule} container nodes inherit some common methods to help work with their children.
 *
 * Note that all containers can store any content. If you write a rule inside a rule, PostCSS will parse it.
 *
 * @link https://github.com/postcss/postcss/blob/master/lib/parser.es6
 * @link https://github.com/postcss/postcss/blob/master/lib/parse.es6
 */
class Parser
{
    const SINGLE_QUOTE = 0x27; //"'"
    const DOUBLE_QUOTE = 0x22; //'"'
    const BACKSLASH = 0x5c; //"\\"
    const SLASH = 0x2f; //"/"
    const NEWLINE = 0x0a; //"\n"
    const SPACE = 0x20; //" "
    const FEED = 0x0c; //"\f"
    const TAB = 0x09; //"\t"
    const CR = 0x0d; //"\r"
    const OPEN_SQUARE = 0x5b; //"["
    const CLOSE_SQUARE = 0x5d; //"]"
    const OPEN_PARENTHESES = 0x28; //"("
    const CLOSE_PARENTHESES = 0x29; //")"
    const OPEN_CURLY = 0x7b; //"{"
    const CLOSE_CURLY = 0x7d; //"}"
    const SEMICOLON = 0x3b; //";"
    const ASTERICK = 0x2a; //"*"
    const COLON = 0x3a; //":"
    const AT = 0x40; //"@"

    const RE_AT_END = '/[ \\n\\t\\r\\f\\{\\(\\)\'"\\\\;\\/\\[\\]#]/';
    const RE_WORD_END = '/[ \\n\\t\\r\\f\\(\\)\\{\\}:;@!\'"\\\\\\]\\[#]|\\/(?=\\*)/';
    const RE_BAD_BRACKET = '%.[\\\/\("\'\n]%';

    /**
     * @param string $css
     * @param array $opts
     *
     * @return Root
     *
     * @throws Exception\CssSyntaxError
     */
    public static function parse($css, array $opts = [])
    {
        if (isset($opts['safe']) && $opts['safe']) {
            throw new \Exception('Option safe was removed. Use parser: require("postcss-safe-parser")');
        }
        $input = new Input($css, $opts);

        $parser = new static($input);
        try {
            $parser->tokenize();
            $parser->loop();
        } catch (Exception\CssSyntaxError $e) {
            if (isset($opts['from']) && $opts['from']) {
                if (preg_match('/\.scss$/i', $opts['from'])) {
                    $e->setMessage($e->getMessage()."\nYou tried to parse SCSS with the standard CSS parser; try again with the postcss-scss parser");
                } elseif (preg_match('/\.less$/i', $opts['from'])) {
                    $e->setMessage($e->getMessage()."\nYou tried to parse Less with the standard CSS parser; try again with the postcss-less parser");
                }
            }
            throw $e;
        }

        return $parser->root;
    }

    /**
     * @var Input
     */
    private $input;

    /**
     * @var int
     */
    private $pos;

    /**
     * @var Root
     */
    private $root;

    /**
     * @var Node
     */
    private $current;

    /**
     * @var string
     */
    private $spaces;

    /**
     * @var bool
     */
    private $semicolon;

    /**
     * @var array|null
     */
    private $tokens = null;

    /**
     * @param Input $input
     */
    public function __construct(Input $input)
    {
        $this->input = $input;
        $this->pos = 0;
        $this->root = new Root();
        $this->current = $this->root;
        $this->spaces = '';
        $this->semicolon = false;
        $this->root->source = [
            'input' => $input,
            'start' => [
                'line' => 1,
                'column' => 1,
            ],
        ];
    }

    /**
     * @throws Exception\CssSyntaxError
     */
    protected function tokenize()
    {
        $this->tokens = static::getTokens($this->input);
    }

    /**
     * @throws Exception\CssSyntaxError
     */
    protected function loop()
    {
        $numTokens = count($this->tokens);
        while ($this->pos < $numTokens) {
            $token = $this->tokens[$this->pos];
            switch ($token[0]) {
                case 'space':
                case ';':
                    $this->spaces .= $token[1];
                    break;

                case '}':
                    $this->end($token);
                    break;

                case 'comment':
                    $this->comment($token);
                    break;

                case 'at-word':
                    $this->atrule($token);
                    break;

                case '{':
                    $this->emptyRule($token);
                    break;

                default:
                    $this->other();
                    break;
            }

            $this->pos += 1;
        }
        $this->endFile();
    }

    /**
     * @param array $token
     */
    protected function comment(array $token)
    {
        $node = new Comment();
        $this->init($node, $token[2], $token[3]);
        $node->source['end'] = ['line' => $token[4], 'column' => $token[5]];

        $text = isset($token[1][4]) ? substr($token[1], 2, -2) : '';
        $len = strlen($text);
        $startOfText = null;
        for ($i = 0; $i < $len; ++$i) {
            if (strpos(" \t\n\r\v\f", $text[$i]) === false) {
                $startOfText = $i;
                break;
            }
        }
        if ($startOfText === null) {
            $node->text = '';
            $node->raws->left = $text;
            $node->raws->right = '';
        } else {
            $node->raws->left = ($startOfText === 0) ? '' : substr($text, 0, $startOfText);
            $numberOfFinalSpaces = 0;
            for ($i = $len - 1; $i >= $startOfText; --$i) {
                if (strpos(" \t\n\r\v\f", $text[$i]) === false) {
                    break;
                }
                ++$numberOfFinalSpaces;
            }
            if ($numberOfFinalSpaces === 0) {
                $node->raws->right = '';
                $node->text = ($startOfText === 0) ? $text : substr($text, $startOfText);
            } else {
                $node->raws->right = substr($text, -$numberOfFinalSpaces);
                $node->text = substr($text, $startOfText, $len - $numberOfFinalSpaces - $startOfText);
            }
        }
    }

    /**
     * @param array $token
     */
    protected function emptyRule(array $token)
    {
        $node = new Rule();
        $this->init($node, $token[2], $token[3]);
        $node->selector = '';
        $node->raws->between = '';
        $this->current = $node;
    }

    /**
     * @throws Exception\CssSyntaxError
     */
    protected function other()
    {
        $end = false;
        $type = null;
        $colon = false;
        $bracket = null;
        $brackets = [];

        $start = $this->pos;
        $numTokens = count($this->tokens);
        while ($this->pos < $numTokens) {
            $token = $this->tokens[$this->pos];
            $type = $token[0];

            if ($type === '(' || $type === '[') {
                if ($bracket === null) {
                    $bracket = $token;
                }
                $brackets[] = ($type === '(') ? ')' : ']';
            } elseif (empty($brackets)) {
                if ($type === ';') {
                    if ($colon) {
                        $this->decl(array_slice($this->tokens, $start, $this->pos + 1 - $start));

                        return;
                    } else {
                        break;
                    }
                } elseif ($type === '{') {
                    $this->rule(array_slice($this->tokens, $start, $this->pos + 1 - $start));

                    return;
                } elseif ($type === '}') {
                    $this->pos -= 1;
                    $end = true;
                    break;
                } elseif ($type === ':') {
                    $colon = true;
                }
            } elseif ($type === $brackets[count($brackets) - 1]) {
                array_pop($brackets);
                if (empty($brackets)) {
                    $bracket = null;
                }
            }

            $this->pos += 1;
        }
        if ($this->pos === $numTokens) {
            $this->pos -= 1;
            $end = true;
        }

        if (!empty($brackets)) {
            $this->unclosedBracket($bracket);
        }

        if ($end && $colon) {
            while ($this->pos > $start) {
                $token = $this->tokens[$this->pos][0];
                if ($token !== 'space' && $token !== 'comment') {
                    break;
                }
                $this->pos -= 1;
            }
            $this->decl(array_slice($this->tokens, $start, $this->pos + 1 - $start));

            return;
        }

        $this->unknownWord($start);
    }

    /**
     * @param array $tokens
     */
    protected function rule(array $tokens)
    {
        array_pop($tokens);

        $node = new Rule();
        $this->init($node, $tokens[0][2], $tokens[0][3]);

        $node->raws->between = $this->spacesFromEnd($tokens);
        $this->raw($node, 'selector', $tokens);
        $this->current = $node;
    }

    /**
     * @param array $tokens
     *
     * @throws Exception\CssSyntaxError
     */
    protected function decl(array $tokens)
    {
        $node = new Declaration();
        $this->init($node);

        $last = $tokens[count($tokens) - 1];
        if ($last[0] === ';') {
            $this->semicolon = true;
            array_pop($tokens);
        }
        if (isset($last[4]) && $last[4]) {
            $node->source['end'] = ['line' => $last[4], 'column' => $last[5]];
        } else {
            $node->source['end'] = ['line' => $last[2], 'column' => $last[3]];
        }

        while ($tokens[0][0] !== 'word') {
            $token = array_shift($tokens);
            $node->raws->before .= $token[1];
        }
        $node->source['start'] = ['line' => $tokens[0][2], 'column' => $tokens[0][3]];

        $node->prop = '';
        while (!empty($tokens)) {
            $type = $tokens[0][0];
            if ($type === ':' || $type === 'space' || $type === 'comment') {
                break;
            }
            $token = array_shift($tokens);
            $node->prop .= $token[1];
        }

        $node->raws->between = '';

        while (!empty($tokens)) {
            $token = array_shift($tokens);
            if ($token[0] === ':') {
                $node->raws->between .= $token[1];
                break;
            } else {
                $node->raws->between .= $token[1];
            }
        }

        if (isset($node->prop[0]) && ($node->prop[0] === '_' || $node->prop[0] === '*')) {
            $node->raws->before .= $node->prop[0];
            $node->prop = substr($node->prop, 1);
        }
        $node->raws->between .= $this->spacesFromStart($tokens);
        $this->precheckMissedSemicolon($tokens);

        for ($i = count($tokens) - 1; $i > 0; --$i) {
            $token = $tokens[$i];
            if ($token[1] === '!important') {
                $node->important = true;
                $string = $this->stringFrom($tokens, $i);
                $string = $this->spacesFromEnd($tokens).$string;
                if ($string !== ' !important') {
                    $node->raws->important = $string;
                }
                break;
            } elseif ($token[1] === 'important') {
                $cache = $tokens;
                $str = '';
                for ($j = $i; $j > 0; --$j) {
                    $type = $cache[$j][0];
                    if (strpos(trim($str), '!') === 0 && $type !== 'space') {
                        break;
                    }
                    $cacheItem = array_pop($cache);
                    $str = $cacheItem[1].$str;
                }
                if (strpos(trim($str), '!') === 0) {
                    $node->important = true;
                    $node->raws->important = $str;
                    $tokens = $cache;
                }
            }

            if ($token[0] !== 'space' && $token[0] !== 'comment') {
                break;
            }
        }

        $this->raw($node, 'value', $tokens);

        if (strpos($node->value, ':') !== false) {
            $this->checkMissedSemicolon($tokens);
        }
    }

    /**
     * @param array $token
     */
    protected function atrule(array $token)
    {
        $node = new AtRule();
        $node->name = isset($token[1][2]) ? substr($token[1], 1) : '';
        if ($node->name === '') {
            $this->unnamedAtrule($node, $token);
        }
        $this->init($node, $token[2], $token[3]);

        $last = false;
        $open = false;
        $params = [];

        $this->pos += 1;
        $numTokens = count($this->tokens);
        while ($this->pos < $numTokens) {
            $token = $this->tokens[$this->pos];
            if ($token[0] === ';') {
                $node->source['end'] = ['line' => $token[2], 'column' => $token[3]];
                $this->semicolon = true;
                break;
            } elseif ($token[0] === '{') {
                $open = true;
                break;
            } elseif ($token[0] === '}') {
                $this->end($token);
                break;
            } else {
                $params[] = $token;
            }

            $this->pos += 1;
        }
        if ($this->pos === $numTokens) {
            $last = true;
        }

        $node->raws->between = $this->spacesFromEnd($params);
        if (!empty($params)) {
            $node->raws->afterName = $this->spacesFromStart($params);
            $this->raw($node, 'params', $params);
            if ($last) {
                $token = $params[count($params) - 1];
                $node->source['end'] = ['line' => $token[4], 'column' => $token[5]];
                $this->spaces = $node->raws->between;
                $node->raws->between = '';
            }
        } else {
            $node->raws->afterName = '';
            $node->params = '';
        }

        if ($open) {
            $node->nodes = [];
            $this->current = $node;
        }
    }

    /**
     * @param array $token
     *
     * @throws Exception\CssSyntaxError
     */
    protected function end(array $token)
    {
        if (!empty($this->current->nodes)) {
            $this->current->raws->semicolon = $this->semicolon;
        }
        $this->semicolon = false;

        $this->current->raws->after = (string) $this->current->raws->after.$this->spaces;
        $this->spaces = '';

        if ($this->current->parent) {
            $this->current->source['end'] = ['line' => $token[2], 'column' => $token[3]];
            $this->current = $this->current->parent;
        } else {
            $this->unexpectedClose($token);
        }
    }

    /**
     * @throws Exception\CssSyntaxError
     */
    protected function endFile()
    {
        if ($this->current->parent) {
            $this->unclosedBlock();
        }
        if (!empty($this->current->nodes)) {
            $this->current->raws->semicolon = $this->semicolon;
        }
        $this->current->raws->after = (string) $this->current->raws->after.$this->spaces;
    }

    /**
     * @param Input $input
     * @param array $options
     *
     * @return array
     *
     * @throws Exception\CssSyntaxError
     */
    public static function getTokens(Input $input, array $options = [])
    {
        $tokens = [];
        $css = (string) $input->css;

        $ignore = isset($options['ignoreErrors']) ? $options['ignoreErrors'] : null;

        $length = strlen($css);
        $offset = -1;
        $line = 1;
        $pos = 0;

        $unclosed = function ($what) use ($input, &$line, &$pos, &$offset) {
            throw $input->error("Unclosed $what", $line, $pos - $offset);
        };

        while ($pos < $length) {
            $code = ord($css[$pos]);

            if ($code === static::NEWLINE || $code === static::FEED || $code === static::CR && ($pos === $length - 1 || ord($css[$pos + 1]) !== static::NEWLINE)) {
                $offset = $pos;
                $line += 1;
            }

            switch ($code) {
                case static::NEWLINE:
                case static::SPACE:
                case static::TAB:
                case static::CR:
                case static::FEED:
                    $next = $pos;
                    do {
                        $next += 1;
                        $code = ($next === $length) ? null : ord($css[$next]);
                        if ($code === static::NEWLINE) {
                            $offset = $next;
                            $line += 1;
                        }
                    } while ($code === static::SPACE || $code === static::NEWLINE || $code === static::TAB || $code === static::CR || $code === static::FEED);
                    $tokens[] = ['space', substr($css, $pos, $next - $pos)];
                    $pos = $next - 1;
                    break;

                case static::OPEN_SQUARE:
                    $tokens[] = ['[', '[', $line, $pos - $offset];
                    break;

                case static::CLOSE_SQUARE:
                    $tokens[] = [']', ']', $line, $pos - $offset];
                    break;

                case static::OPEN_CURLY:
                    $tokens[] = ['{', '{', $line, $pos - $offset];
                    break;

                case static::CLOSE_CURLY:
                    $tokens[] = ['}', '}', $line, $pos - $offset];
                    break;

                case static::COLON:
                    $tokens[] = [':', ':', $line, $pos - $offset];
                    break;

                case static::SEMICOLON:
                    $tokens[] = [';', ';', $line, $pos - $offset];
                    break;

                case static::OPEN_PARENTHESES:
                    $prev = empty($tokens) ? '' : $tokens[count($tokens) - 1][1];
                    $n = ($pos < $length - 1) ? ord($css[$pos + 1]) : null;
                    if ($prev === 'url' && $n !== static::SINGLE_QUOTE && $n !== static::DOUBLE_QUOTE && $n !== static::SPACE && $n !== static::NEWLINE && $n !== static::TAB && $n !== static::FEED && $n !== static::CR) {
                        $next = $pos;
                        do {
                            $escaped = false;
                            $next = strpos($css, ')', $next + 1);
                            if ($next === false) {
                                if ($ignore) {
                                    $next = $pos;
                                    break;
                                } else {
                                    $unclosed('bracket');
                                }
                            }
                            $escapePos = $next;
                            while (ord($css[$escapePos - 1]) === static::BACKSLASH) {
                                $escapePos -= 1;
                                $escaped = !$escaped;
                            }
                        } while ($escaped);

                        $tokens[] = ['brackets', substr($css, $pos, $next + 1 - $pos), $line, $pos - $offset, $line, $next - $offset];
                        $pos = $next;
                    } else {
                        $next = strpos($css, ')', $pos + 1);
                        $content = substr($css, $pos, $next + 1 - $pos);

                        if ($next === false || preg_match(static::RE_BAD_BRACKET, $content)) {
                            $tokens[] = ['(', '(', $line, $pos - $offset];
                        } else {
                            $tokens[] = ['brackets', $content, $line, $pos - $offset, $line, $next - $offset];
                            $pos = $next;
                        }
                    }
                    break;

                    case static::CLOSE_PARENTHESES:
                        $tokens[] = [')', ')', $line, $pos - $offset];
                        break;

                    case static::SINGLE_QUOTE:
                    case static::DOUBLE_QUOTE:
                        $quote = $code === static::SINGLE_QUOTE ? '\'' : '"';
                        $next = $pos;
                        do {
                            $escaped = false;
                            $next = strpos($css, $quote, $next + 1);
                            if ($next === false) {
                                if ($ignore) {
                                    $next = $pos + 1;
                                    break;
                                } else {
                                    $unclosed('quote');
                                }
                            }
                            $escapePos = $next;
                            while (ord($css[$escapePos - 1]) === static::BACKSLASH) {
                                $escapePos -= 1;
                                $escaped = !$escaped;
                            }
                        } while ($escaped);

                        $content = substr($css, $pos, $next + 1 - $pos);
                        $lines = explode("\n", $content);
                        $last = count($lines) - 1;

                        if ($last > 0) {
                            $nextLine = $line + $last;
                            $nextOffset = $next - strlen($lines[$last]);
                        } else {
                            $nextLine = $line;
                            $nextOffset = $offset;
                        }

                        $tokens[] = ['string', substr($css, $pos, $next + 1 - $pos), $line, $pos - $offset, $nextLine, $next - $nextOffset];

                        $offset = $nextOffset;
                        $line = $nextLine;
                        $pos = $next;
                        break;

                    case static::AT:
                        if (!preg_match(static::RE_AT_END, $css, $rxMatches, 0, $pos + 1)) {
                            $next = $length - 1;
                        } else {
                            $next = strpos($css, $rxMatches[0], $pos + 1) - 1;
                        }
                        $tokens[] = ['at-word', substr($css, $pos, $next + 1 - $pos), $line, $pos - $offset, $line, $next - $offset];
                        $pos = $next;
                        break;

                    case static::BACKSLASH:
                        $next = $pos;
                        $escape = true;
                        while (($next < ($length - 1)) && ord($css[$next + 1]) === static::BACKSLASH) {
                            $next += 1;
                            $escape = !$escape;
                        }
                        $code = ($next < ($length - 1)) ? ord($css[$next + 1]) : null;
                        if ($escape && ($code !== static::SLASH && $code !== static::SPACE && $code !== static::NEWLINE && $code !== static::TAB && $code !== static::CR && $code !== static::FEED)) {
                            $next += 1;
                        }
                        $tokens[] = ['word', substr($css, $pos, $next + 1 - $pos), $line, $pos - $offset, $line, $next - $offset];
                        $pos = $next;
                        break;

                    default:
                        if ($code === static::SLASH && $pos < ($length - 1) && ord($css[$pos + 1]) === static::ASTERICK) {
                            $next = strpos($css, '*/', $pos + 2);
                            if ($next === false) {
                                if ($ignore) {
                                    $next = $length;
                                } else {
                                    $unclosed('comment');
                                }
                            } else {
                                ++$next;
                            }

                            $content = substr($css, $pos, $next + 1 - $pos);
                            $lines = explode("\n", $content);
                            $last = count($lines) - 1;

                            if ($last > 0) {
                                $nextLine = $line + $last;
                                $nextOffset = $next - strlen($lines[$last]);
                            } else {
                                $nextLine = $line;
                                $nextOffset = $offset;
                            }

                            $tokens[] = ['comment', $content, $line, $pos - $offset, $nextLine, $next - $nextOffset];

                            $offset = $nextOffset;
                            $line = $nextLine;
                            $pos = $next;
                        } else {
                            if (!preg_match(static::RE_WORD_END, $css, $rxMatches, 0, $pos + 1)) {
                                $next = $length - 1;
                            } else {
                                $next = strpos($css, $rxMatches[0], $pos + 1) - 1;
                            }
                            $tokens[] = ['word', substr($css, $pos, $next + 1 - $pos), $line, $pos - $offset, $line, $next - $offset];
                            $pos = $next;
                        }

                        break;
                }

            ++$pos;
        }

        return $tokens;
    }

    // Helpers

    /**
     * @param Node $node
     * @param int|null $line
     * @param int|null $column
     */
    protected function init(Node $node, $line = null, $column = null)
    {
        $this->current->push($node);

        $node->source = ['start' => ['line' => $line, 'column' => $column], 'input' => $this->input];
        $node->raws->before = $this->spaces;
        $this->spaces = '';
        if ($node->type !== 'comment') {
            $this->semicolon = false;
        }
    }

    /**
     * @param Node $node
     * @param string $prop
     * @param array $tokens
     */
    protected function raw(Node $node, $prop, array $tokens)
    {
        $length = count($tokens);
        $value = '';
        $clean = true;
        for ($i = 0; $i < $length; $i += 1) {
            $token = $tokens[$i];
            $type = $token[0];
            if ($type === 'comment' || $type === 'space' && $i === $length - 1) {
                $clean = false;
            } else {
                $value .= $token[1];
            }
        }
        if (!$clean) {
            $raw = '';
            foreach ($tokens as $token) {
                $raw .= $token[1];
            }
            $node->raws->$prop = ['value' => $value, 'raw' => $raw];
        }
        $node->$prop = $value;
    }

    /**
     * @param array $tokens
     *
     * @return string
     */
    protected function spacesFromEnd(array &$tokens)
    {
        $spaces = '';
        $lastTokenType = null;
        $numTokens = count($tokens);
        while ($numTokens !== 0) {
            $lastTokenType = $tokens[$numTokens - 1][0];
            if ($lastTokenType !== 'space' && $lastTokenType !== 'comment') {
                break;
            }
            $token = array_pop($tokens);
            --$numTokens;
            $spaces = $token[1].$spaces;
        }

        return $spaces;
    }

    /**
     * @param array $tokens
     *
     * @return string
     */
    protected function spacesFromStart(array &$tokens)
    {
        $spaces = '';
        while (!empty($tokens)) {
            $next = $tokens[0][0];
            if ($next !== 'space' && $next !== 'comment') {
                break;
            }
            $token = array_shift($tokens);
            $spaces .= $token[1];
        }

        return $spaces;
    }

    /**
     * @param array $tokens
     * @param int $from
     *
     * @return string
     */
    protected function stringFrom(array &$tokens, $from)
    {
        $result = '';
        for ($i = $from, $n = count($tokens); $i < $n; ++$i) {
            $result .= $tokens[$i][1];
        }
        array_splice($tokens, $from, $n - $from);

        return $result;
    }

    /**
     * @param array $tokens
     *
     * @return int|bool
     *
     * @throws Exception\CssSyntaxError
     */
    protected function colon(array $tokens)
    {
        $brackets = 0;
        $prev = null;
        for ($i = 0, $n = count($tokens); $i < $n; ++$i) {
            $token = $tokens[$i];
            $type = $token[0];

            if ($type === '(') {
                $brackets += 1;
            } elseif ($type === ')') {
                $brackets -= 1;
            } elseif ($brackets === 0 && $type === ':') {
                if ($prev === null) {
                    $this->doubleColon($token);
                } elseif ($prev[0] === 'word' && $prev[1] === 'progid') {
                    continue;
                } else {
                    return $i;
                }
            }

            $prev = $token;
        }

        return false;
    }

    // Errors

    /**
     * @param array $tokens
     *
     * @throws Exception\CssSyntaxError
     */
    protected function unclosedBracket(array $bracket)
    {
        throw $this->input->error('Unclosed bracket', $bracket[2], $bracket[3]);
    }

    /**
     * @param array $tokens
     *
     * @throws Exception\CssSyntaxError
     */
    protected function unknownWord($start)
    {
        $token = $this->tokens[$start];
        throw $this->input->error('Unknown word', $token[2], $token[3]);
    }

    /**
     * @param array $tokens
     *
     * @throws Exception\CssSyntaxError
     */
    protected function unexpectedClose(array $token)
    {
        throw $this->input->error('Unexpected }', $token[2], $token[3]);
    }

    /**
     * @param array $tokens
     *
     * @throws Exception\CssSyntaxError
     */
    protected function unclosedBlock()
    {
        $pos = (isset($this->current->source) && $this->current->source['start']) ? $this->current->source['start'] : null;
        throw $this->input->error('Unclosed block', $pos ? $pos['line'] : null, $pos ? $pos['column'] : null);
    }

    /**
     * @param array $tokens
     *
     * @throws Exception\CssSyntaxError
     */
    protected function doubleColon(array $token)
    {
        throw $this->input->error('Double colon', $token[2], $token[3]);
    }

    protected function unnamedAtrule($node, $token)
    {
        throw $this->input->error('At-rule without name', $token[2], $token[3]);
    }

    /**
     * @param array $tokens
     */
    protected function precheckMissedSemicolon(array &$tokens)
    {
        // Hook for Safe Parser
        //$tokens;
    }

    /**
     * @param array $tokens
     *
     * @throws Exception\CssSyntaxError
     */
    protected function checkMissedSemicolon(array &$tokens)
    {
        $colon = $this->colon($tokens);
        if ($colon === false) {
            return;
        }
        $founded = 0;
        for ($j = $colon - 1; $j >= 0; --$j) {
            $token = $tokens[$j];
            if ($token[0] !== 'space') {
                $founded += 1;
                if ($founded === 2) {
                    break;
                }
            }
        }
        throw $this->input->error('Missed semicolon', $token[2], $token[3]);
    }
}
