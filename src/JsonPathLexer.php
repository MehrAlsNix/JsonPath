<?php

namespace MehrAlsNix\JsonPath;
use Doctrine\Common\Lexer\AbstractLexer;

/**
 * Class Lexer
 * @package MehrAlsNix\JsonPath
 */
class JsonPathLexer extends AbstractLexer
{
    // All tokens that are not valid identifiers must be < 100
    const T_NONE                = 1;
    const T_INTEGER             = 2;
    const T_STRING              = 3;
    const T_FLOAT               = 4;
    const T_CLOSE_PARENTHESIS   = 5;
    const T_OPEN_PARENTHESIS    = 6;
    const T_COMMA               = 7;
    const T_DIVIDE              = 8;
    const T_DOT                 = 9;

    const T_IDENTIFIER          = 100;
    const T_ALL                 = 101;
    const T_ROOT                = 102;
    const T_CURRENT             = 103;
    const T_CHILD               = 104;
    const T_RECURSIVE_DESCENT   = 105;
    const T_WILDCARD            = 106;
    const T_SEPARATOR           = 107;

    public function __construct($input = null)
    {
        if ($input !== null) {
            $this->setInput($input);
        }
    }

    /**
     * Lexical catchable patterns.
     *
     * @return array
     */
    protected function getCatchablePatterns()
    {
        return [
            '\$',
            '\.\.?',
            '\w+',
            '\s* \d+ [\d,\s]+',
            '[-\d:]+',
            '\s* \( .+? \) \s*',
            '\s* \?\(.+?\) \s*',
            '\s* \' (.+?) \' \s*',
            '\s* " (.+?) " \s*'
        ];
    }

    /**
     * Lexical non-catchable patterns.
     *
     * @return array
     */
    protected function getNonCatchablePatterns()
    {
        return ['\s+', '(.)'];
    }

    /**
     * Retrieve token type. Also processes the token value if necessary.
     *
     * @param string $value
     *
     * @return integer
     */
    protected function getType(&$value)
    {
        $type = self::T_NONE;
        switch (true) {
            /*
            // Recognize numeric values
            case (is_numeric($value)):
                if (strpos($value, '.') !== false || stripos($value, 'e') !== false) {
                    return self::T_FLOAT;
                }
                return self::T_INTEGER;
            // Recognize quoted strings
            case ($value[0] === "'"):
                $value = str_replace("''", "'", substr($value, 1, strlen($value) - 2));
                return self::T_STRING;
            // Recognize identifiers
            case (ctype_alpha($value[0]) || $value[0] === '_'):
                $name = 'Doctrine\ORM\Query\Lexer::T_' . strtoupper($value);
                if (defined($name)) {
                    $type = constant($name);
                    if ($type > 100) {
                        return $type;
                    }
                }
                return self::T_IDENTIFIER;
            // Recognize symbols
            */
            case ($value === '$'): return self::T_ROOT;
            case ($value === '..'): return self::T_RECURSIVE_DESCENT;
            case ($value === '.'): return self::T_DOT;
            case ($value === ','): return self::T_COMMA;
            case ($value === '('): return self::T_OPEN_PARENTHESIS;
            case ($value === ')'): return self::T_CLOSE_PARENTHESIS;
            case ($value === ';'): return self::T_SEPARATOR;
            // Default
            default:
                // Do nothing
        }
        return $type;
    }
}
