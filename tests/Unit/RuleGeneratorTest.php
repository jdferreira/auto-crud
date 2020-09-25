<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Validation\RuleGenerator;

class RuleGeneratorTest extends TestCase
{
    /** @test */
    public function it_ignores_primary_key_columns()
    {
        $table = $this->mockTable('students', [
            'id' => ['primaryKey' => true],
        ]);

        $rule = new RuleGenerator($table, 'id');

        $this->assertEquals(null, $rule->generate());
    }

    /** @test */
    public function it_ignores_timestamp_columns_and_soft_deleted_at()
    {
        $table = $this->mockTable('students', [
            'created_at' => ['type' => Type::DATETIME],
            'updated_at' => ['type' => Type::DATETIME],
            'deleted_at' => ['type' => Type::DATETIME],
        ]);

        foreach (['created_at', 'updated_at', 'deleted_at'] as $column) {
            $rule = new RuleGenerator($table, $column);

            $this->assertEquals(null, $rule->generate());
        }
    }

    /** @test */
    public function it_detects_required_or_nullable()
    {
        $table = $this->mockTable('students', [
            'name' => ['required' => true],
            'patronus_form' => ['required' => false],
        ]);

        $this->assertRulesContain($table, 'name', "'required'");
        $this->assertRulesContain($table, 'patronus_form', "'nullable'");
    }

    /** @test */
    public function it_knows_of_email_and_uuid_column_names()
    {
        $table = $this->mockTable('muggle_things', [
            'email' => [],
            'uuid' => [],
        ]);

        $this->assertRulesContain($table, 'email', "'email:rfc'");
        $this->assertRulesContain($table, 'uuid', "'uuid'");
    }

    /** @test */
    public function it_uses_specific_formatters_only_when_columns_are_textual()
    {
        $table = $this->mockTable('muggle_things', [
            'email' => ['type' => Type::DECIMAL],
            'uuid' => ['type' => Type::DATE],
        ]);

        $this->assertRulesNotContain($table, 'email', "'email:rfc'");
        $this->assertRulesNotContain($table, 'uuid', "'uuid'");
    }

    /** @test */
    public function it_generates_rules_for_column_types()
    {
        $table = $this->mockTable('students', [
            'birthday' => ['type' => Type::DATE],
            'height' => ['type' => Type::DECIMAL],
            'has_pet' => ['type' => Type::BOOLEAN],
            'current_year' => ['type' => Type::INTEGER],
            'letter_sent_at' => ['type' => Type::DATETIME],
            'preferred_lunch_time' => ['type' => Type::TIME],
        ]);

        $this->assertRulesContain($table, 'birthday', "'date_format:Y-m-d'");
        $this->assertRulesContain($table, 'height', "'regex:/^[+-]?(?:\d+\.?|\d*\.\d+)$/'");
        $this->assertRulesContain($table, 'has_pet', "'boolean'");
        $this->assertRulesContain($table, 'current_year', "'integer'");
        $this->assertRulesContain($table, 'letter_sent_at', "'date'");
        $this->assertRulesContain($table, 'preferred_lunch_time', "'date_format:H:i:s'");
    }

    /** @test */
    public function it_accepts_all_values_for_string_columns()
    {
        $table = $this->mockTable('schools', [
            'name' => ['type' => Type::STRING, 'required' => true],
            'motto' => ['type' => Type::TEXT, 'required' => true],
        ]);

        $this->assertEquals(["'required'"], (new RuleGenerator($table, 'name'))->makeRules());
        $this->assertEquals(["'required'"], (new RuleGenerator($table, 'motto'))->makeRules());
    }

    /** @test */
    public function it_generates_rules_for_enum_columns()
    {
        $table = $this->mockTable('houses', [
            'color' => [
                'enum' => ['red', 'green', 'blue', 'yellow'],
            ],
        ]);

        $this->assertRulesContain($table, 'color', "'in:red,green,blue,yellow'");
    }

    /** @test */
    public function it_generates_rules_for_unique_columns()
    {
        $table = $this->mockTable('schools', [
            'name' => ['unique' => true],
        ]);

        $rule = new RuleGenerator($table, 'name');

        $this->assertContains(
            'Rule::unique(\'schools\')->ignore($model)',
            $rule->makeRules()
        );

        $this->assertTrue($rule->needsModel());
    }

    /** @test */
    public function it_generates_rules_for_foreign_keys()
    {
        $table = $this->mockTable('pets', [
            'owner_id' => ['reference' => ['students', 'id']],
            'species' => ['reference' => ['species', 'species']],
        ]);

        $this->assertRulesContain($table, 'owner_id', "'exists:students,id'");

        // Test for when the name of the foreign column is the same as the name
        // of the field under validation.
        $this->assertRulesContain($table, 'species', "'exists:species'");
    }

    /** @test */
    public function it_implodes_rules_if_possible()
    {
        $table = $this->mockTable('schools', [
            'foundation_year' => ['required' => true, 'type' => Type::INTEGER],
            'motto' => ['required' => true, 'unique' => true],
        ]);

        $this->assertEquals(
            ["'required|integer'"],
            (new RuleGenerator($table, 'foundation_year'))->generate()
        );

        $this->assertEquals(
            [
                '[',
                "    'required',",
                "    Rule::unique('schools')->ignore(\$model),",
                ']',
            ],
            (new RuleGenerator($table, 'motto'))->generate()
        );
    }

    private function assertRulesContain($table, $column, $value)
    {
        $this->assertContains(
            $value,
            (new RuleGenerator($table, $column))->makeRules()
        );
    }

    private function assertRulesNotContain($table, $column, $value)
    {
        $this->assertNotContains(
            $value,
            (new RuleGenerator($table, $column))->makeRules()
        );
    }

    private function assertRulesEqual($table, $column, $value)
    {
        $this->assertEquals(
            $value,
            (new RuleGenerator($table, $column))->makeRules()
        );
    }
}
