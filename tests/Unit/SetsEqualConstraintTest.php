<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\SetsEqualConstraint;
use PHPUnit\Framework\Constraint\LogicalNot;

class SetsEqualConstraintTest extends TestCase
{
    /** @test */
    public function it_matches_on_equal_arrays()
    {
        static::assertThat(
            [1, 2, 3],
            new SetsEqualConstraint([1, 2, 3])
        );
    }

    /** @test */
    public function it_disregards_order()
    {
        static::assertThat(
            [3, 2, 1],
            new SetsEqualConstraint([1, 2, 3])
        );
    }

    /** @test */
    public function it_detects_missing_elements()
    {
        static::assertThat(
            [1, 2, 3],
            new LogicalNot(new SetsEqualConstraint([1, 2, 3, 4]))
        );
    }

    /** @test */
    public function it_detects_extra_elements()
    {
        static::assertThat(
            [1, 2, 3, 4],
            new LogicalNot(new SetsEqualConstraint([1, 2, 3]))
        );
    }

    /** @test */
    public function test_case_has_helper_method()
    {
        $this->assertSetsEqual(
            [1, 2, 3, 4],
            [4, 3, 2, 1]
        );
    }
}
