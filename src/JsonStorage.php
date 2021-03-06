<?php

namespace MehrAlsNix\JsonPath;

class JsonStorage
{
    /** @var array $emptyArray */
    private static $emptyArray = [];

    /**
     * @var array
     */
    private $data;

    /**
     * @param string|array|\stdClass $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($data)
    {
        $this->setData($data);
    }

    /**
     * Sets JsonStore's manipulated data
     * @param string|array|\stdClass $data
     *
     * @throws \InvalidArgumentException
     */
    public function setData($data)
    {
        $this->data = $data;
        if (is_string($this->data)) {
            $this->data = json_decode($this->data, true);
        } elseif (is_object($data)) {
            $this->data = json_decode(json_encode($this->data), true);
        } elseif (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf('Invalid data type in JsonStore. Expected object, array or string, got %s', gettype($data)));
        }
    }

    /**
     * JsonEncoded version of the object
     * @return string
     */
    public function toString()
    {
        return json_encode($this->data);
    }

    /**
     * Returns the given json string to object
     * @return \stdClass
     */
    public function toObject()
    {
        return json_decode(json_encode($this->data));
    }

    /**
     * Returns the given json string to array
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Sets the value for all elements matching the given JsonPath expression
     * @param string $expr JsonPath expression
     * @param mixed $value Value to set
     * @return bool returns true if success
     */
    public function set($expr, $value)
    {
        $get = $this->get($expr);
        if ($res =& $get) {
            foreach ($res as &$r) {
                $r = $value;
            }
            return true;
        }
        return false;
    }

    /**
     * Gets elements matching the given JsonPath expression
     * @param string $expr JsonPath expression
     * @param bool $unique Gets unique results or not
     * @return array
     */
    public function get($expr, $unique = false)
    {
        if ((($exprs = $this->normalizedFirst($expr)) !== false)
            && (is_array($exprs) || $exprs instanceof \Traversable)
        ) {
            $values = array();
            foreach ($exprs as $expr) {
                $o =& $this->data;
                $keys = preg_split(
                    "/([\"'])?\]\[([\"'])?/",
                    preg_replace(array("/^\\$\[[\"']?/", "/[\"']?\]$/"), "", $expr)
                );
                for ($i = 0; $i < count($keys); $i++) {
                    $o =& $o[$keys[$i]];
                }
                $values[] = &$o;
            }
            if (true === $unique) {
                if (!empty($values) && is_array($values[0])) {
                    array_walk($values, function (&$value) {
                        $value = json_encode($value);
                    });
                    $values = array_unique($values);
                    array_walk($values, function (&$value) {
                        $value = json_decode($value, true);
                    });
                    return array_values($values);
                }
                return array_unique($values);
            }
            return $values;
        }
        return self::$emptyArray;
    }

    private function normalizedFirst($expr)
    {
        if ($expr === '') {
            return false;
        } else {
            if (preg_match("/^\$(\[([0-9*]+|'[-a-zA-Z0-9_ ]+')\])*$/", $expr)) {
                print('normalized: ' . $expr);
                return $expr;
            } else {
                $res = (new JsonPath($this->data))->query($expr, array('resultType' => 'PATH'));
                return $res;
            }
        }
    }

    /**
     * Adds one or more elements matching the given json path expression
     * @param string $parentexpr JsonPath expression to the parent
     * @param mixed $value Value to add
     * @param string $name Key name
     * @return bool returns true if success
     */
    public function add($parentexpr, $value, $name = '')
    {
        $get = $this->get($parentexpr);
        if ($parents =& $get) {
            foreach ($parents as &$parent) {
                $parent = is_array($parent) ? $parent : array();
                if ($name !== '') {
                    $parent[$name] = $value;
                } else {
                    $parent[] = $value;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Removes all elements matching the given jsonpath expression
     * @param string $expr JsonPath expression
     * @return bool returns true if success
     */
    public function remove($expr)
    {
        if ((($exprs = $this->normalizedFirst($expr)) !== false) &&
            (is_array($exprs) || $exprs instanceof \Traversable)
        ) {
            foreach ($exprs as $expr) {
                $o =& $this->data;
                $keys = preg_split(
                    "/([\"'])?\]\[([\"'])?/",
                    preg_replace(array("/^\\$\[[\"']?/", "/[\"']?\]$/"), '', $expr)
                );
                for ($i = 0; $i < count($keys) - 1; $i++) {
                    $o =& $o[$keys[$i]];
                }
                unset($o[$keys[$i]]);
            }
            return true;
        }
        return false;
    }
}
