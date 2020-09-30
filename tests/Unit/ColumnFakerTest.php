<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\VersionChecker;
use Ferreira\AutoCrud\Generators\ColumnFaker;
use Ferreira\AutoCrud\Database\TableInformation;

class ColumnFakerTest extends TestCase
{
    // TODO: Mock with HP fun

    private function faker(TableInformation $table, string $column)
    {
        return app(ColumnFaker::class, ['table' => $table, 'column' => $column]);
    }

    /** @test */
    public function it_ignores_primary_key_columns()
    {
        $table = $this->mockTable('tablename', [
            'id' => ['primaryKey' => true],
        ]);

        $faker = $this->faker($table, 'id');

        $this->assertEquals('', $faker->fake());
    }

    /** @test */
    public function it_ignores_timestamp_columns()
    {
        $table = $this->mockTable('tablename');

        foreach (['created_at', 'updated_at'] as $column) {
            $faker = $this->faker($table, $column);

            $this->assertEquals('', $faker->fake());
        }
    }

    /** @test */
    public function it_fakes_soft_delete_times()
    {
        $table = $this->mockTable('tablename', [
            'deleted_at' => ['type' => Type::DATETIME, 'required' => false],
        ]);

        $faker = $this->faker($table, 'deleted_at');

        $this->assertEquals(
            "\$faker->optional(0.9)->passthrough(\$faker->dateTimeBetween('-10 years', 'now')->format('Y-m-d H:i:s'))",
            $faker->fake()
        );
    }

    /** @test */
    public function it_fakes_based_on_database_type()
    {
        $fakes = [
            Type::INTEGER => 'numberBetween(0, 10000)',
            Type::BOOLEAN => 'boolean',
            Type::DATETIME => "dateTimeBetween('-10 years', 'now')->format('Y-m-d H:i:s')",
            Type::DATE => 'date',
            Type::TIME => 'time',
            Type::DECIMAL => "numerify('%##.##')",
            Type::STRING => 'sentence',
            Type::TEXT => 'text',
        ];

        foreach ($fakes as $type => $fake) {
            $this->assertFakesType($type, $fake);
        }
    }

    private function assertFakesType(string $type, string $fake)
    {
        $table = $this->mockTable('tablename', [
            'column' => ['type' => $type],
        ]);

        $faker = $this->faker($table, 'column');

        $this->assertEquals("\$faker->$fake", $faker->fake());
    }

    /** @test */
    public function it_fakes_enum_types()
    {
        $table = $this->mockTable('tablename', [
            'color' => ['enum' => ['red', 'green', 'blue']],
        ]);

        $faker = $this->faker($table, 'color');

        $this->assertEquals(
            '$faker->randomElement([\'red\', \'green\', \'blue\'])',
            $faker->fake()
        );
    }

    /** @test */
    public function it_uses_faker_methods_when_faker_has_a_method_with_the_name_of_the_column()
    {
        // Complete equivalence
        $this->assertFakerPropertyIsUsed('name', 'name');
        $this->assertFakerPropertyIsUsed('address', 'address');
        $this->assertFakerPropertyIsUsed('password', 'password');

        // Snake case to camel case
        $this->assertFakerPropertyIsUsed('mac_address', 'macAddress');
        $this->assertFakerPropertyIsUsed('country_code', 'countryCode');

        // Prefix 'random'
        $this->assertFakerPropertyIsUsed('digit', 'randomDigit');

        // TODO: This test (and the following method) could probably be
        // refactored to be use a single mocked table and construct the fakers
        // for each column.
    }

    private function assertFakerPropertyIsUsed(string $column, string $fakerProperty)
    {
        $this->assertNotNull(\Faker\Factory::create()->getFormatter($fakerProperty));

        $table = $this->mockTable('tablename', [
            $column => [],
        ]);

        $faker = $this->faker($table, $column);

        $this->assertEquals("\$faker->$fakerProperty", $faker->fake());
    }

    /** @test */
    public function it_prioritizes_specific_types_over_column_names()
    {
        $specificTypes = [
            Type::INTEGER,
            Type::BOOLEAN,
            Type::DATETIME,
            Type::DATE,
            Type::TIME,
            Type::DECIMAL,
        ];

        foreach ($specificTypes as $type) {
            $table = $this->mockTable('tablename', [
                'name' => ['type' => $type],
            ]);

            $this->assertNotEquals(
                '$faker->name',
                $this->faker($table, 'name')->fake(),
                "Column of type $type produced the wrong faker."
            );
        }
    }

    /** @test */
    public function it_fakes_nulls_on_nullable_columns_sometimes()
    {
        $table = $this->mockTable('tablename', [
            'column' => ['required' => false],
        ]);

        $faker = $this->faker($table, 'column');

        $this->assertEquals(
            '$faker->optional(0.9)->sentence',
            $faker->fake()
        );
    }

    /** @test */
    public function it_fakes_null_on_nullable_foreign_keys_sometimes()
    {
        $table = $this->mockTable('students', [
            'pet' => ['required' => false, 'reference' => ['pets', 'id']],
        ]);

        $faker = $this->faker($table, 'pet');

        $this->assertCodeContains('
            $faker->optional(0.9)->passthrough(function () {
                return factory(Pet::class)->create()->id;
            })
        ', $faker->fake());
    }

    /** @test */
    public function it_uses_the_correct_factory_syntax_for_laravel_eight()
    {
        app(VersionChecker::class)->mockVersion('8.0.0');

        $table = $this->mockTable('students', [
            'pet' => ['required' => false, 'reference' => ['pets', 'id']],
        ]);

        $faker = $this->faker($table, 'pet');

        $this->assertCodeContains('
            $this->faker->optional(0.9)->passthrough(function () {
                return Pet::factory()->create()->id;
            })
        ', $faker->fake());
    }

    /** @test */
    public function it_mixes_optional_columns_with_other_fakers()
    {
        $table = $this->mockTable('tablename', [
            'email' => ['required' => false],
            'column' => ['required' => false, 'type' => Type::DECIMAL],
        ]);

        $faker = $this->faker($table, 'email');
        $this->assertEquals('$faker->optional(0.9)->email', $faker->fake());

        $faker = $this->faker($table, 'column');
        $this->assertEquals('$faker->optional(0.9)->numerify(\'%##.##\')', $faker->fake());
    }

    /** @test */
    public function it_fakes_unique_values_on_columns_with_unique_indices()
    {
        $table = $this->mockTable('tablename', [
            'email' => ['unique' => true],
        ]);

        $faker = $this->faker($table, 'email');

        $this->assertEquals(
            '$faker->unique()->email',
            $faker->fake()
        );
    }

    /** @test */
    public function it_fakes_unique_nullable_columns()
    {
        $table = $this->mockTable('tablename', [
            'email' => ['unique' => true, 'required' => false],
        ]);

        $faker = $this->faker($table, 'email');

        $this->assertEquals(
            '$faker->randomFloat() <= 0.9 ? $faker->unique()->email : null',
            $faker->fake()
        );
    }

    /** @test */
    public function it_creates_a_new_model_for_foreign_keys()
    {
        $table = $this->mockTable('tablename', [
            'user_id' => ['reference' => ['users', 'id']],
        ]);

        $faker = $this->faker($table, 'user_id');

        $this->assertEquals(
            implode("\n", [
                'function () {',
                '    return factory(User::class)->create()->id;',
                '}',
            ]),
            $faker->fake()
        );
    }

    /** @test */
    public function it_does_away_with_the_unique_modifier_on_unique_columns_with_a_foreign_key()
    {
        $table = $this->mockTable('tablename', [
            'user_id' => ['unique' => true, 'reference' => ['users', 'id']],
        ]);

        $faker = $this->faker($table, 'user_id');

        $this->assertEquals(
            implode("\n", [
                'function () {',
                '    return factory(User::class)->create()->id;',
                '}',
            ]),
            $faker->fake()
        );
    }

    /** @test */
    public function it_knows_that_the_column_references_another_table_after_faking()
    {
        $table = $this->mockTable('users', [
            'name' => [],
            'avatar_id' => ['reference' => ['avatars', 'id']],
            'parent' => ['required' => false, 'reference' => ['users', 'id']],
        ]);

        $name = $this->faker($table, 'name');
        $name->fake();
        $this->assertNull($name->referencedTable());

        $avatar = $this->faker($table, 'avatar_id');
        $avatar->fake();
        $this->assertEquals('avatars', $avatar->referencedTable());

        $parent = $this->faker($table, 'parent');
        $parent->fake();
        $this->assertEquals('users', $parent->referencedTable());
    }

    /** @test */
    public function it_correctly_handles_nullable_columns_with_complex_faker_logic()
    {
        $table = $this->mockTable('students', [
            'latest_detention' => [
                'required' => false,
                'type' => Type::DATETIME,
            ],
        ]);

        $faker = $this->faker($table, 'latest_detention');

        $this->assertEquals(
            "\$faker->optional(0.9)->passthrough(\$faker->dateTimeBetween('-10 years', 'now')->format('Y-m-d H:i:s'))",
            $faker->fake()
        );
    }
}
