<?php

namespace Tests\Unit;

use Exception;
use Tests\TestCase;
use Tests\ArrayHasSubset;

class ArrayHasSubsetTest extends TestCase
{
    /**
     * @var array
     */
    private $array;

    public function setUp(): void
    {
        $this->array = [
            'one' => 'one',
            'two' => 'two',
            'three' => 'three',
            'four' => 'four',
        ];
    }

    /** @test */
    public function it_asserts_array_subsets()
    {
        $this->assertThat(
            $this->array,
            new ArrayHasSubset(['one' => 'one'])
        );
    }

    /** @test */
    public function it_accepts_wrong_order()
    {
        $this->assertThat(
            $this->array,
            new ArrayHasSubset(['two' => 'two', 'one' => 'one'])
        );
    }

    /** @test */
    public function it_throws_on_non_fully_associative_arrays()
    {
        $this->assertException(Exception::class, function () {
            new ArrayHasSubset([
                'one' => 'one',
                'two', // no key!
            ]);
        });
    }

    /** @test */
    public function it_trivially_allows_empty_subsets()
    {
        $this->assertThat(
            $this->array,
            new ArrayHasSubset([])
        );

        $this->assertThat(
            [],
            new ArrayHasSubset([])
        );
    }

    /** @test */
    public function test_case_has_helper_method()
    {
        $this->assertArrayHasSubset([], []);
    }
}
