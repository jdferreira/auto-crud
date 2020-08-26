<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Validation\RuleGenerator;

class RuleGeneratorTest extends TestCase
{
    /** @test */
    public function it_accepts_a_table_information_and_a_column_name_as_arguments()
    {
        $table = $this->mockTable('tablename');

        $faker = new RuleGenerator($table, 'column');

        $this->assertInstanceOf(RuleGenerator::class, $faker);
    }

    /** @test */
    public function it_ignores_autoincrement_columns()
    {
        $table = $this->mockTable('tablename', [
            'id' => ['autoincrement' => true],
        ]);

        $rule = new RuleGenerator($table, 'id');

        $this->assertEquals(null, $rule->generate());
    }

    /** @test */
    public function it_ignores_timestamp_columns_and_soft_deleted_at()
    {
        $table = $this->mockTable('tablename');

        foreach (['created_at', 'updated_at', 'deleted_at'] as $column) {
            $rule = new RuleGenerator($table, $column);

            $this->assertEquals(null, $rule->generate());
        }
    }

    /** @test */
    public function it_detects_required_or_nullable()
    {
        $table = $this->mockTable('tablename', [
            'name' => ['required' => true],
            'email' => ['required' => false],
        ]);

        $rule = new RuleGenerator($table, 'name');
        $this->assertContains("'required'", $rule->makeRules());

        $rule = new RuleGenerator($table, 'email');
        $this->assertContains("'nullable'", $rule->makeRules());
    }

    /** @test */
    public function it_generates_nullable_rule_for_columns_with_default_value()
    {
        $table = $this->mockTable('tablename', [
            'count' => ['required' => true, 'hasDefault' => true],
        ]);

        $rule = new RuleGenerator($table, 'count');
        $this->assertContains("'nullable'", $rule->makeRules());
    }

    /** @test */
    public function it_knows_of_email_and_uuid_column_names()
    {
        $table = $this->mockTable('tablename', [
            'email' => [],
            'uuid' => [],
        ]);

        $customs = [
            'email' => 'email:rfc',
            'uuid' => 'uuid',
        ];

        foreach ($customs as $key => $value) {
            $rule = new RuleGenerator($table, $key);

            $this->assertContains("'$value'", $rule->makeRules());
        }
    }

    /** @test */
    public function it_generates_rules_for_column_types()
    {
        $validation = [
            Type::INTEGER => 'integer',
            Type::BOOLEAN => 'boolean',
            Type::DATETIME => 'date',
            Type::DATE => 'date_format:Y-m-d',
            Type::TIME => 'date_format:H:i:s',
        ];

        $table = $this->mockTable('tablename', [
            'integer' => ['type' => Type::INTEGER],
            'boolean' => ['type' => Type::BOOLEAN],
            'datetime' => ['type' => Type::DATETIME],
            'date' => ['type' => Type::DATE],
            'time' => ['type' => Type::TIME],
        ]);

        foreach ($validation as $key => $value) {
            $rule = new RuleGenerator($table, $key);

            $this->assertContains("'$value'", $rule->makeRules());
        }
    }

    /** @test */
    public function it_accepts_all_values_for_string_and_binary_columns()
    {
        $table = $this->mockTable('tablename', [
            'string' => ['type' => Type::STRING, 'required' => true],
            'text' => ['type' => Type::TEXT, 'required' => true],
            'binary' => ['type' => Type::BINARY, 'required' => true],
        ]);

        foreach (['string', 'text', 'binary'] as $column) {
            $rule = new RuleGenerator($table, $column);

            $this->assertEquals(["'required'"], $rule->makeRules());
        }
    }

    /** @test */
    public function it_generates_regex_rules_for_decimal_columns()
    {
        $table = $this->mockTable('tablename', [
            'decimal' => ['type' => Type::DECIMAL],
        ]);

        $rule = new RuleGenerator($table, 'decimal');

        $this->assertContains("'regex:/^(?:\d+\.?|\d*\.\d+)$/'", $rule->makeRules());
    }

    /** @test */
    public function it_generates_rules_for_enum_columns()
    {
        $table = $this->mockTable('tablename', [
            'color' => ['enum' => ['red', 'green', 'blue']],
        ]);

        $rule = new RuleGenerator($table, 'color');

        $this->assertContains("'in:red,green,blue'", $rule->makeRules());
    }

    /** @test */
    public function it_generates_rules_for_unique_columns()
    {
        $table = $this->mockTable('tablename', [
            'email' => ['unique' => true],
        ]);

        $rule = new RuleGenerator($table, 'email');

        $this->assertContains(
            'Rule::unique(\'tablename\')->ignore($model)',
            $rule->makeRules()
        );

        $this->assertTrue($rule->needsModel());
    }

    /** @test */
    public function it_generates_rules_for_foreign_keys()
    {
        $table = $this->mockTable('tablename', [
            'user_id' => ['reference' => ['users', 'id']],
            'state' => ['reference' => ['states', 'state']],
        ]);

        $rule = new RuleGenerator($table, 'user_id');
        $this->assertContains("'exists:users,id'", $rule->makeRules());

        // Test where the name of the foreign column is the same as the name of
        // the field under validation.

        $rule = new RuleGenerator($table, 'state');
        $this->assertContains("'exists:states'", $rule->makeRules());
    }

    /** @test */
    public function it_implodes_rules_if_possible()
    {
        $table = $this->mockTable('users', [
            'birthday' => ['required' => true, 'type' => Type::DATE],
            'username' => ['required' => true, 'unique' => true],
        ]);

        $rule = new RuleGenerator($table, 'birthday');
        $this->assertEquals(["'required|date_format:Y-m-d'"], $rule->generate());

        $rule = new RuleGenerator($table, 'username');
        $this->assertEquals([
            '[',
            "    'required',",
            "    Rule::unique('users')->ignore(\$model),",
            ']',
        ], $rule->generate());
    }
}
