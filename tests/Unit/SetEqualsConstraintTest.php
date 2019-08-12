<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\SetEqualsConstraint;
use PHPUnit\Framework\Constraint\LogicalNot;

class SetEqualsConstraintTest extends TestCase
{
    /** @test */
    public function it_matches_on_equal_arrays()
    {
        static::assertThat(
            [1, 2, 3],
            new SetEqualsConstraint([1, 2, 3])
        );
    }

    /** @test */
    public function it_disregards_order()
    {
        static::assertThat(
            [3, 2, 1],
            new SetEqualsConstraint([1, 2, 3])
        );
    }

    /** @test */
    public function it_detects_missing_elements()
    {
        static::assertThat(
            [1, 2, 3],
            new LogicalNot(new SetEqualsConstraint([1, 2, 3, 4]))
        );
    }

    /** @test */
    public function it_detects_extra_elements()
    {
        static::assertThat(
            [1, 2, 3, 4],
            new LogicalNot(new SetEqualsConstraint([1, 2, 3]))
        );
    }
}
