<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Column;
use Ferreira\AutoCrud\Validation\RuleGenerator;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class RuleGeneratorTest extends TestCase
{
    private function mockColumn(array $options = [])
    {
        $mockedType = $this->mock(Type::class, function ($mock) {
            $mock->shouldReceive('getName')->andReturn('unrecognized_column_type_name');
        });

        $options = [
            'type' => Arr::get($options, 'type', $mockedType),
            'name' => Arr::get($options, 'name', 'random_column_name'),
            'autoincrement' => Arr::get($options, 'autoincrement', false),
            'required' => Arr::get($options, 'required', true),
            'default' => Arr::get($options, 'default', null),
        ];

        if (is_string($options['type'])) {
            $options['type'] = Type::getType($options['type']);
        }

        return $this->mock(Column::class, function ($mock) use ($options) {
            $mock->shouldReceive('getAutoincrement')->andReturn($options['autoincrement']);
            $mock->shouldReceive('getName')->andReturn($options['name']);
            $mock->shouldReceive('getType')->andReturn($options['type']);
            $mock->shouldReceive('getNotnull')->andReturn($options['required']);
            $mock->shouldReceive('getDefault')->andReturn($options['default']);
        });
    }

    public function makeColumnsUnique()
    {
        $this->mock(DatabaseInformation::class, function ($mock) {
            $mock->shouldReceive('unique')->andReturn(true);
            $mock->shouldReceive('foreignKeysReferences')->andReturn(null);
        });
    }

    /** @test */
    public function it_accepts_a_tablename_and_column()
    {
        $rule = new RuleGenerator(
            'tablename',
            $this->mockColumn()
        );

        $this->assertInstanceOf(
            RuleGenerator::class,
            $rule
        );
    }

    /** @test */
    public function it_ignores_autoincrement_columns()
    {
        $rule = new RuleGenerator(
            'tablename',
            $this->mockColumn(['autoincrement' => true])
        );

        $this->assertEquals('', $rule->generate());
    }

    /** @test */
    public function it_ignores_timestamp_columns()
    {
        foreach (['created_at', 'updated_at'] as $name) {
            $rule = new RuleGenerator(
                'tablename',
                $this->mockColumn(['name' => $name, 'type' => Type::DATETIME])
            );

            $this->assertEquals('', $rule->generate());
        }
    }

    /** @test */
    public function it_detects_required_or_nullable()
    {
        $nullable = new RuleGenerator(
            'tablename',
            $this->mockColumn(['required' => false])
        );

        $required = new RuleGenerator(
            'tablename',
            $this->mockColumn(['required' => true])
        );

        $this->assertContains("'nullable'", $nullable->makeRules());
        $this->assertContains("'required'", $required->makeRules());
    }

    /** @test */
    public function it_knows_of_email_date_and_uuid_column_names()
    {
        $customs = [
            'email' => 'email:rfc',
            'uuid' => 'uuid',
        ];

        foreach ($customs as $key => $value) {
            $rule = new RuleGenerator(
                'tablename',
                $this->mockColumn(['name' => $key])
            );

            $this->assertContains("'$value'", $rule->makeRules());
        }
    }

    /** @test */
    public function it_generates_rules_for_column_types()
    {
        $validation = [
            // Integers
            Type::BIGINT => 'integer',
            Type::INTEGER => 'integer',
            Type::SMALLINT => 'integer',

            // Boolean
            Type::BOOLEAN => 'boolean',

            // Date, time and related
            Type::DATETIME => 'date',
            Type::DATETIME_IMMUTABLE => 'date',
            Type::DATETIMETZ => 'date',
            Type::DATETIMETZ_IMMUTABLE => 'date',

            Type::DATE => 'date_format:Y-m-d',
            Type::DATE_IMMUTABLE => 'date_format:Y-m-d',

            Type::TIME => 'date_format:H:i:s',
            Type::TIME_IMMUTABLE => 'date_format:H:i:s',

            // Floats
            Type::FLOAT => 'numeric',

            // Other
            Type::GUID => 'uuid',

            // TODO: This test is missing the following database type, which I am not sure how to handle.
            //   - Type::TARRAY
            //   - Type::SIMPLE_ARRAY
            //   - Type::JSON_ARRAY
            //   - Type::JSON
            //   - Type::DATEINTERVAL
            //   - Type::OBJECT
        ];

        foreach ($validation as $key => $value) {
            $rule = new RuleGenerator(
                'tablename',
                $this->mockColumn(['type' => $key])
            );

            $this->assertContains("'$value'", $rule->makeRules());
        }
    }

    /** @test */
    public function it_accepts_all_values_for_string_and_binary_columns()
    {
        $types = [
            Type::STRING,
            Type::TEXT,
            Type::BINARY,
            Type::BLOB,
        ];

        foreach ($types as $type) {
            $rule = new RuleGenerator(
                'tablename',
                $this->mockColumn([
                    'type' => $type,
                    'required' => true,
                ])
            );

            $this->assertEquals(["'required'"], $rule->makeRules());
        }
    }

    /** @test */
    public function it_generates_rules_for_enum_columns()
    {
        // This particular test needs the machinery of the back-end migration
        // since we cannot easily mock an enum column (which is Doctrine's
        // fault, actually).

        $this->migrations = __DIR__ . '/../migrations';

        $this->runMigrations();

        $rule = new RuleGenerator(
            'products',
            $this->db->table('products')->column('type')
        );

        $this->assertContains("'in:food,stationery,other'", $rule->makeRules());

        // Undo the migrations, as the rest of the class has no need for them

        $this->rollbackMigrations();

        $this->migrations = null;
    }

    /** @test */
    public function it_generates_regex_rules_for_decimal_columns()
    {
        $rule = new RuleGenerator(
            'tablename',
            $this->mockColumn([
                'type' => Type::DECIMAL,
            ])
        );

        $this->assertContains("'regex:/^(?:\d+\.?|\d*\.\d+)$/'", $rule->makeRules());
    }

    /** @test */
    public function it_generates_rules_for_foreign_keys()
    {
        // Test where the name of the foreign column is not the same as the name
        // of the field under validation.
        $this->mock(DatabaseInformation::class, function ($mock) {
            $mock
                ->shouldReceive('foreignKeysReferences')
                ->with('products', 'owner_id')
                ->andReturn(['users', 'id']);
            $mock->shouldReceive('unique')->andReturn(false);
        });

        $rule = new RuleGenerator('products', $this->mockColumn(['name' => 'owner_id']));

        $this->assertContains("'exists:users,id'", $rule->makeRules());

        // Test where the name of the foreign column is the same as the name of
        // the field under validation.
        $this->mock(DatabaseInformation::class, function ($mock) {
            $mock
                ->shouldReceive('foreignKeysReferences')
                ->with('products', 'state')
                ->andReturn(['states', 'state']);
            $mock->shouldReceive('unique')->andReturn(false);
        });

        $rule = new RuleGenerator('products', $this->mockColumn(['name' => 'state']));

        $this->assertContains("'exists:states'", $rule->makeRules());
    }

    /** @test */
    public function it_generates_rules_for_unique_columns()
    {
        $this->makeColumnsUnique();

        $rule = new RuleGenerator('tablename', $this->mockColumn());

        $this->assertContains(
            'Rule::unique(\'tablename\')->ignore($model)',
            $rule->makeRules()
        );

        $this->assertTrue($rule->needsModel());
    }
}
