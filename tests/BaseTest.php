<?php

namespace MehrAlsNix\JsonPath\Tests;

use MehrAlsNix\JsonPath\JsonStorage;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * BaseTest Class.
 *
 * ```
 * A TestCase defines the fixture to run multiple tests.
 *
 * To define a TestCase
 *
 *   1) Implement a subclass of PHPUnit_Framework_TestCase.
 *   2) Define instance variables that store the state of the fixture.
 *   3) Initialize the fixture state by overriding setUp().
 *   4) Clean-up after a test by overriding tearDown().
 *
 * Each test runs in its own fixture so there can be no side effects
 * among test runs.
 *
 * Here is an example:
 *
 * <code>
 * <?php
 * class MathTest extends PHPUnit_Framework_TestCase
 * {
 *     public $value1;
 *     public $value2;
 *
 *     protected function setUp()
 *     {
 *         $this->value1 = 2;
 *         $this->value2 = 3;
 *     }
 * }
 * ?>
 * </code>
 *
 * For each test implement a method which interacts with the fixture.
 * Verify the expected results with assertions specified by calling
 * assert with a boolean.
 *
 * <code>
 * <?php
 * public function testPass()
 * {
 *     $this->assertTrue($this->value1 + $this->value2 == 5);
 * }
 * ?>
 * </code>
 * ```
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
