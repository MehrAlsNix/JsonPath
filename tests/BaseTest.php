<?php

namespace MehrAlsNix\JsonPath\Tests;

use MehrAlsNix\JsonPath\JsonStorage;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * BaseTest Class.
 */
abstract class BaseTest extends TestCase
{
    /** @var string $json */
    protected $json;

    /** @var JsonStorage $jsonStore */
    protected $jsonStore;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
        $this->json = file_get_contents(__DIR__ . '/_files/test.json');

        $this->jsonStore = new JsonStorage($this->json);
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    public function tearDown()
    {
        $this->jsonStore = null;
        $this->json = null;
    }

    public function assertFixtureSet($element, $data)
    {
        $expected = include __DIR__ . "/_fixtures/{$element}.php";
        $this->assertEquals($expected, $data);
    }
}
