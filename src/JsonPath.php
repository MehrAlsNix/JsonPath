<?php

namespace MehrAlsNix\JsonPath;
use MehrAlsNix\JsonPath\Filter\Element;
use Traversable;

/**
 * Class JsonPath
 * @package MehrAlsNix\JsonPath
 */
class JsonPath  implements \ArrayAccess, \JsonSerializable
{
    /**
     * @var array
     */
    private static $keywords = ['=', ')', '!', '<', '>'];
    /**
     * @var string
     */
    private static $recursiveDescent = '..';
    /**
     * @var string
     */
    private static $wildcard = '*';

    /**
     * @var \RecursiveArrayIterator
     */
    private $dataStore;

    /**
     * @var string
     */
    private $resultType = 'Value';
    /**
     * @var array
     */
    private $result = [];

    private $lexer;

    /**
     * @param array $json
     */
    public function __construct(array $json)
    {
        $this->dataStore = new \RecursiveArrayIterator($json);
        $this->lexer     = new JsonPathLexer();
    }

    /**
     * @param string $expr
     * @param array|null $args
     * @return array
     */
    public function query($expr, $args = null)
    {
        $this->resultType = $args ? $args['resultType'] : 'VALUE';

        if ($expr && $this->dataStore && ($this->resultType === 'VALUE' || $this->resultType === 'PATH')) {
            $this->lexer->setInput($expr);
            $this->parse();
        }

        return $this->result;
    }

    /**
     * @param string $expr
     * @param mixed $val
     * @param string $path
     */
    private function parse()
    {
        while ($this->lexer->moveNext()) {
            $lookahead = $this->lexer->lookahead;
            if ($this->lexer->isNextToken(JsonPathLexer::T_RECURSIVE_DESCENT)) {
                $element = $this->lexer->glimpse();
                var_dump(iterator_to_array(new Element($this->dataStore, 'book')));
            }
        };
        //var_dump($expr, $this->lexer->peek(), $this->lexer);
        die();
        if ($expr !== '') {
            $x = explode(';', $expr);
            $loc = array_shift($x);
            $x = implode(';', $x);

            if ($val instanceof \ArrayObject && array_key_exists($loc, $val)) {
                $this->trace($x, $val[$loc], $path . ';' . $loc);
            } elseif ($loc === self::$wildcard) {
                $this->walk(
                    $loc,
                    $x,
                    $val,
                    $path,
                    function ($m, $l, $x, $v, $p) {
                        $this->trace($m . ';' . $x, $v, $p);
                    }
                );
            } elseif ($loc === self::$recursiveDescent) {
                $this->trace($x, $val, $path);
                $this->walk(
                    $loc,
                    $x,
                    $val,
                    $path,
                    function ($m, $l, $x, $v, $p) {
                        if (is_array($v[$m])) {
                            $this->trace('..;' . $x, $v[$m], $p . ';' . $m);
                        }
                    }
                );
            } elseif ($this->isScriptExpression($loc)) {
                if ($loc === '(@.length-1)') {
                    $this->trace('-1:;' . $x, $val, $path);
                } else {
                    $ex = $this->evalx($loc, $val, substr($path, strrpos($path, ';') + 1));
                    $this->trace($ex . ';' . $x, $val, $path);
                }
            } elseif ($this->isFilterExpression($loc)) {
                $this->processFilterExpression($loc, $x, $val, $path);
            } elseif ($this->isArraySliceOperator($loc)) {
                $this->slice($loc, $x, $val, $path);
            } elseif (strpos($loc, ',')) { // [name1,name2,...]
                for ($s = preg_split('/\'?,\'?/', $loc), $i = 0, $n = count($s); $i < $n; $i++)
                    $this->trace($s[$i] . ";" . $x, $val, $path);
            }
        } else {
            $this->store($path, $val);
        }
    }

    /**
     * @param string $loc
     * @return bool
     */
    private function isArraySliceOperator($loc)
    {
        return (bool) preg_match("/^(-?\d*):(-?\d*):?(-?\d*)$/", $loc);
    }

    /**
     * @param string $loc
     * @return bool
     */
    private function isScriptExpression($loc)
    {
        return (bool) preg_match("/^\(.*?\)$/", $loc);
    }

    /**
     * @param $loc
     * @return bool
     */
    private function isFilterExpression($loc)
    {
        return (bool) preg_match("/^\?\(.*?\)$/", $loc);
    }

    /**
     * @param string $loc
     * @param string $x
     * @param mixed $val
     * @param string $path
     */
    private function processFilterExpression($loc, $x, $val, $path)
    {
        $this->walk(
            $loc,
            $x,
            $val,
            $path,
            function ($m, $l, $x, $v, $p) {
                if ($this->evalx(preg_replace("/^\?\((.*?)\)$/", "$1", $l), $v[$m])) {
                    $this->trace($m . ';' . $x, $v, $p);
                }
            }
        );
    }

    /**
     * @param string $loc
     * @param string $expr
     * @param mixed $val
     * @param string $path
     * @param callable $f
     */
    private function walk($loc, $expr, $val, $path, $f)
    {
        foreach ($val as $m => $v) {
            call_user_func($f, $m, $loc, $expr, $val, $path);
        }
    }

    /**
     * @param string $x filter
     * @param array $v node
     *
     * @param string $vname
     * @return string
     */
    private function evalx($x, $v, $vname = null)
    {
        $name = "";
        $expr1 = preg_replace(array('/\$/', '/@.([a-zA-Z\']*)/'), array('$this->obj', '$v[\'$1\']'), $x);
        $expr = preg_replace("#\(([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\)#", "['$1']", $expr1);

        $res = eval("\$name = (string) $expr;");

        if ($res === false) {
            print("(jsonPath) SyntaxError: $expr");
        } else {
            return $name;
        }
    }

    /**
     * @param $loc
     * @param $expr
     * @param $v
     * @param $path
     */
    private function slice($loc, $expr, $v, $path)
    {
        $s = explode(':', preg_replace("/^(-?\d*):(-?\d*):?(-?\d*)$/", "$1:$2:$3", $loc));
        $len = count($v);
        $start = (int) $s[0] ? $s[0] : 0;
        $end = (int) $s[1] ? $s[1] : $len;
        $step = (int) $s[2] ? $s[2] : 1;
        $start = ($start < 0) ? max(0, $start + $len) : min($len, $start);
        $end = ($end < 0) ? max(0, $end + $len) : min($len, $end);
        for ($i = $start; $i < $end; $i += $step) {
            $this->trace($i . ';' . $expr, $v, $path);
        }
    }

    /**
     * @param $p
     * @param $v
     * @return bool
     */
    private function store($p, $v)
    {
        if ($p) {
            $this->result[] = $this->resultType === 'PATH' ? $this->asPath($p) : $v;
        }

        return (bool) $p;
    }

    /**
     * Builds json path expression
     * @param string $path
     * @return string
     */
    private function asPath($path)
    {
        $expr = explode(";", $path);
        $fullPath = "$";
        for ($i = 1, $n = count($expr); $i < $n; $i++) {
            $fullPath .= preg_match("/^[0-9*]+$/", $expr[$i]) ? ("[" . $expr[$i] . "]") : ("['" . $expr[$i] . "']");
        }

        return $fullPath;
    }

    /**
     * Pushs the filter into the list
     * @param string $filter
     * @return string
     */
    private function tempFilters($filter)
    {
        $f = $filter[1];
        $elements = explode('\'', $f);

        // Hack to make "dot" works on filters
        $numElements = count($elements);
        for ($i = 0, $m = 0; $i < $numElements; $i++) {
            if ($m & 1 === 0) {
                if ($i > 0 && substr($elements[$i - 1], 0, 1) === '\\') {
                    continue;
                }

                $e = explode('.', $elements[$i]);
                $str = '';
                $first = true;
                foreach ($e as $substr) {
                    if ($first) {
                        $str = $substr;
                        $first = false;
                        continue;
                    }

                    $end = null;
                    if (false !== $pos = $this->strpos_array($substr, self::$keywords)) {
                        list($substr, $end) = [substr($substr, 0, $pos), substr($substr, $pos, strlen($substr))];
                    }

                    $str .= '[' . $substr . ']';
                    if (null !== $end) {
                        $str .= $end;
                    }
                }
                $elements[$i] = $str;
            }

            $m++;
        }

        return sprintf('[#%s]', array_push($this->result, implode('\'', $elements)) - 1);
    }

    /**
     * Search one of the given needs in the array
     * @param string $haystack
     * @param array $needles
     * @return bool|string
     */
    private function strpos_array($haystack, array $needles)
    {
        $closer = 10000;
        foreach ($needles as $needle) {
            if (false !== $pos = strpos($haystack, $needle) && $pos < $closer) {
                $closer = $pos;
            }
        }

        return 10000 === $closer ? false : $closer;
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->dataStore->getArrayCopy();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->dataStore);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->dataStore[$key];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->dataStore[$offset] = $value;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        if ($this->dataStore->offsetExists($offset)) {
            unset($this->dataStore[$offset]);
        }
    }
}
