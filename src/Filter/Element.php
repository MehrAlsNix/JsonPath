<?php

namespace MehrAlsNix\JsonPath\Filter;

class Element extends \FilterIterator
{
    protected $element;

    public function __construct(\RecursiveIterator $recursiveIter, $element)
    {
        $this->element = $element;
        parent::__construct($recursiveIter);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Check whether the current element of the iterator is acceptable
     * @link http://php.net/manual/en/filteriterator.accept.php
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept()
    {
        return ($this->hasChildren() || $this->key() == $this->element);
    }

    public function getChildren()
    {
        return new self($this->getInnerIterator()->getChildren(), $this->element);
    }
}
