<?php

namespace MehrAlsNix\JsonPath;

/**
 * Class JsonPath
 * @package MehrAlsNix\JsonPath
 */
class JsonPath
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
     * @var
     */
    private $obj;
    /**
     * @var string
     */
    private $resultType = 'Value';
    /**
     * @var array
     */
    private $result = [];

    /**
     * @param
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($array)
    {
        if (is_object($array)) {
            throw new \InvalidArgumentException(
                'You sent an object, not an array.'
            );
        }

        $this->obj = $array;
    }

    /**
     * @param string $expr
     * @param array|null $args
     * @return array|bool
     */
    public function query($expr, $args = null)
    {
        $this->resultType = $args ? $args['resultType'] : 'VALUE';
        $x = $this->normalize($expr);

        if ($expr && $this->obj && ($this->resultType === 'VALUE' || $this->resultType === 'PATH')) {
            $this->trace(preg_replace('/^\$;/', '', $x), $this->obj, '$');
            if (count($this->result)) {
                return $this->result;
            }

            return false;
        }
    }

    /**
     * normalize path expression
     *
     * @param $expression
     * @return mixed
     */
    private function normalize($expression)
    {
        // Replaces filters by #0 #1...
        $expression = preg_replace_callback(
            ["/[\['](\??\(.*?\))[\]']/", "/\['(.*?)'\]/"],
            [&$this, 'tempFilters'],
            $expression
        );

        // ; separator between each elements
        $expression = preg_replace(
            ["/'?\.'?|\['?/", "/;;;|;;/", "/;$|'?\]|'$/"],
            [';', ';..;', ''],
            $expression
        );

        // Restore filters
        $expression = preg_replace_callback('/#(\d+)/', function ($filter) { return $this->result[$filter[1]]; }, $expression);
        // result array was temporarily used as a buffer ..
        $this->result = [];
        return $expression;
    }

    /**
     * @param string $expr
     * @param mixed $val
     * @param string $path
     */
    private function trace($expr, $val, $path)
    {
        if ($expr !== '') {
            $x = explode(';', $expr);
            $loc = array_shift($x);
            $x = implode(';', $x);

            if (is_array($val) && array_key_exists($loc, $val)) {
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
        $expr = preg_replace(array('/\$/', '/@/'), array('$this->obj', '$v'), $x);
        $expr = preg_replace("#\[([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\]#", "['$1']", $expr);

        $res = eval("\$name = $expr;");

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
            $this->trace($i . ";" . $expr, $v, $path);
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
}
