<?php

namespace MehrAlsNix\JsonPath\Tests;

use MehrAlsNix\JsonPath\Tests\BaseTest as TestCase;

class JsonStoreTest extends TestCase
{
    /**
     * @test
     */
    public function setData()
    {
        $this->assertEquals($this->jsonStore->toString(), json_encode(json_decode($this->json, true)), JSON_PRETTY_PRINT);
        $this->assertEquals($this->jsonStore->toArray(), json_decode($this->json, true));

        $new = ['a' => 'b'];
        $this->jsonStore->setData($new);

        $this->assertEquals($this->jsonStore->toArray(), $new);
        $this->assertNotEquals($this->jsonStore->toArray(), json_decode($this->json, true));
    }

    /**
     * @test
     */
    public function getLast()
    {
        $data1 = $this->jsonStore->get("$..book[-1:].isbn");
        $data2 = $this->jsonStore->get("$..book[(@.length-1)].isbn");

        $expected = ['0-395-19395-8'];
        $this->assertEquals($expected, $data1);
        $this->assertEquals($expected, $data2);
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
    public function findPosition()
    {
        $books = $this->jsonStore->get("$..book[:2]");
        $this->assertFixtureSet('firstTwoBooks', $books);
    }

    /**
     * @test
     */
    public function findPositionBySet()
    {
        $this->assertFixtureSet(
            'firstTwoBooks',
            $this->jsonStore->get("$..book[0,1]")
        );
    }

    /**
     * @test
     */
    public function findLessThan()
    {
        $this->assertFixtureSet(
            'lessThan',
            $this->jsonStore->get("$..book[?(@.price<8.99)]")
        );
    }

    /**
     * @test
     */
    public function getAllChildMembers()
    {
        $this->assertFixtureSet('storage', $this->jsonStore->get("$.store.*"));
    }

    /**
     * @test
     */
    public function getAllMembers()
    {
        $this->assertFixtureSet('book', $this->jsonStore->get("$..*")[0]);
    }
}
