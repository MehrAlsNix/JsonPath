<?php

namespace MehrAlsNix\JsonPath\Tests;

use MehrAlsNix\JsonPath\JsonStorage;
use PHPUnit_Framework_TestCase as TestCase;

class JsonStoreTest extends TestCase
{
    /** @var string $json */
    private $json;

    /** @var JsonStorage $jsonStore */
    private $jsonStore;

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

    /**
     * @test
     */
    public function setData()
    {
        $this->assertEquals($this->jsonStore->toArray(), json_decode($this->json, true));

        $new = ['a' => 'b'];
        $this->jsonStore->setData($new);

        $this->assertEquals($this->jsonStore->toArray(), $new);
        $this->assertNotEquals($this->jsonStore->toArray(), json_decode($this->json, true));
    }

    /**
     * @test
     */
    public function getAllByKey()
    {
        $data = $this->jsonStore->get("$..book.*.category");
        $expected = ['reference', 'fiction', 'fiction', 'fiction'];
        $this->assertEquals($expected, $data);

        $data = $this->jsonStore->get("$..category");
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function getAllByKeyUnique()
    {
        $data = $this->jsonStore->get("$..book.*.category", true);
        $expected = ['reference', 'fiction'];
        $this->assertEquals($expected, $data);

        $data = $this->jsonStore->get("$..category", true);
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function getAllByKeyFiltered()
    {
        $data = $this->jsonStore->get("$..book[(@.code='01.02')].category");
        $expected = ['fiction'];
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function getLast()
    {
        $data = $this->jsonStore->get("$..book[-1:].isbn");
        $expected = ['0-395-19395-8'];
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function findPosition()
    {
        $data = $this->jsonStore->get("$..book[:2]");
        $expected = [
            [
                'category' => 'reference',
                'author' => 'Nigel Rees',
                'title' => 'Sayings of the Century',
                'price' => 8.95
            ],
            [
                'category' => 'fiction',
                'author' => 'Evelyn Waugh',
                'title' => 'Sword of Honour',
                'price' => 12.99,
                'code' => '01.02'
            ]
        ];
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function findPositionBySet()
    {
        $data = $this->jsonStore->get("$..book[0,1]");
        $expected = [
            [
                'category' => 'reference',
                'author' => 'Nigel Rees',
                'title' => 'Sayings of the Century',
                'price' => 8.95
            ],
            [
                'category' => 'fiction',
                'author' => 'Evelyn Waugh',
                'title' => 'Sword of Honour',
                'price' => 12.99,
                'code' => '01.02'
            ]
        ];
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function findLessThan()
    {
        $data = $this->jsonStore->get("$..book[?(@.price<8.98)]");
        $expected = [
            [
                'category' => 'reference',
                'author' => 'Nigel Rees',
                'title' => 'Sayings of the Century',
                'price' => 8.95
            ]
        ];
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function getAllChildMembers()
    {
        $data = $this->jsonStore->get("$.store.*");
        $expected = [
            [
                [
                    'category' => 'reference',
                    'author' => 'Nigel Rees',
                    'title' => 'Sayings of the Century',
                    'price' => 8.95
                ],
                [
                    'category' => 'fiction',
                    'author' => 'Evelyn Waugh',
                    'title' => 'Sword of Honour',
                    'price' => 12.99,
                    'code' => '01.02'
                ],
                [
                    'category' => 'fiction',
                    'author' => 'Herman Melville',
                    'title' => 'Moby Dick',
                    'isbn' => '0-553-21311-3',
                    'price' => 8.99
                ],
                [
                    'category' => 'fiction',
                    'author' => 'J. R. R. Tolkien',
                    'title' => 'The Lord of the Rings',
                    'isbn' => '0-395-19395-8',
                    'price' => 22.99
                ]
            ],
            [
                'color' => 'red',
                'price' => 19.95
            ]
        ];
        $this->assertEquals($expected, $data);
    }
}
