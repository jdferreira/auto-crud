<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\CodeContainsConstraint;
use PHPUnit\Framework\Constraint\LogicalNot;

/**
 * @ignore
 */
class CodeContainsConstraintTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->code = $this->files->get(__DIR__ . '/inputs/code.php');
    }

    /** @test */
    public function it_asserts_code_contains()
    {
        static::assertThat($this->code, new CodeContainsConstraint("
            public function abc()
            {
                return 'abc';
            }
        "));
    }

    /** @test */
    public function it_can_handle_indentation_leveles()
    {
        if (true) {
            // This irrelevant if-clause is here only to ensure that we test
            // the assertion with different indentation levels, mimicking
            // its use by developers in any situation arising in code.
            static::assertThat($this->code, new CodeContainsConstraint("
                public function abc()
                {
                    return 'abc';
                }
            "));
        }
    }

    /** @test */
    public function it_handles_single_lines()
    {
        static::assertThat($this->code, new CodeContainsConstraint('private $field;'));
    }

    /** @test */
    public function it_handles_non_flat_indentation()
    {
        static::assertThat($this->code, new CodeContainsConstraint('
            step1();
            step2();
            step3();
        '));
    }

    /** @test */
    public function it_handles_empty_non_indented_lines()
    {
        static::assertThat($this->code, new CodeContainsConstraint('
            with_some();

            empty($nonIndented);

            lines();
        '));
    }

    /** @test */
    public function it_handles_code_with_various_indentation_levels()
    {
        static::assertThat($this->code, new CodeContainsConstraint("
            private function def(\$method): int
            {
                if (\$method === 'def') {
                    return 1 + \$this->field; // Comment
                } else {
                    return 0;
                }
            }
        "));
    }

    /** @test */
    public function it_handles_deep_code()
    {
        static::assertThat(
            $this->code,
            new CodeContainsConstraint('return 1 + $this->field; // Comment')
        );
    }

    /** @test */
    public function it_failes_on_non_existing_code()
    {
        static::assertThat(
            $this->code,
            new LogicalNot(new CodeContainsConstraint('non existing code'))
        );
    }

    /** @test */
    public function it_works_only_with_whole_lines_and_not_partials()
    {
        static::assertThat(
            $this->code,
            new LogicalNot(new CodeContainsConstraint('// Comment'))
        );

        static::assertThat($this->code, new LogicalNot(new CodeContainsConstraint("
            if (\$method === 'def') {
                return 1 + \$this->field; // Comment
            }
        ")));
    }

    /** @test */
    public function it_requires_correct_relative_indentation_levels()
    {
        static::assertThat($this->code, new LogicalNot(new CodeContainsConstraint("
            public function abc()
            {
            return 'abc';
            }
        ")));
    }

    /** @test */
    public function test_case_has_helper_method()
    {
        $this->assertCodeContains('echo $var;', 'echo $var;');

        $this->assertCodeNotContains('echo $var;', '');
    }
}
