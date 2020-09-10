<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Generators\ColumnFaker;

class ColumnFakerTest extends TestCase
{
    /** @test */
    public function it_accepts_a_table_information_and_a_column_name_as_arguments()
    {
        $table = $this->mockTable('tablename');

        $faker = new ColumnFaker($table, 'column');

        $this->assertInstanceOf(ColumnFaker::class, $faker);
    }

    /** @test */
    public function it_ignores_autoincrement_columns()
    {
        $table = $this->mockTable('tablename', [
            'id' => ['autoincrement' => true],
        ]);

        $faker = new ColumnFaker($table, 'id');

        $this->assertEquals('', $faker->fake());
    }

    /** @test */
    public function it_ignores_timestamp_columns()
    {
        $table = $this->mockTable('tablename');

        foreach (['created_at', 'updated_at'] as $column) {
            $faker = new ColumnFaker($table, $column);

            $this->assertEquals('', $faker->fake());
        }
    }

    /** @test */
    public function it_fakes_soft_delete_times()
    {
        $table = $this->mockTable('tablename', [
            'deleted_at' => ['type' => Type::DATETIME, 'required' => false],
        ]);

        $faker = new ColumnFaker($table, 'deleted_at');

        $this->assertEquals(
            '$faker->optional(0.9)->dateTimeBetween(\'-10 years\', \'now\')->format(\'Y-m-d H:i:s\')',
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
            Type::BINARY => 'passthrough(random_bytes(1024))',
            // Type::ENUM uses a different mechanism
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

        $faker = new ColumnFaker($table, 'column');

        $this->assertEquals("\$faker->$fake", $faker->fake());
    }

    /** @test */
    public function it_fakes_enum_types()
    {
        $table = $this->mockTable('tablename', [
            'color' => ['enum' => ['red', 'green', 'blue']],
        ]);

        $faker = new ColumnFaker($table, 'color');

        $this->assertEquals(
            '$faker->randomElement([\'red\', \'green\', \'blue\'])',
            $faker->fake()
        );
    }

    /** @test */
    public function it_defaults_to_the_string_null()
    {
        $table = $this->mockTable('tablename', [
            'column' => ['type' => Type::UNRECOGNIZED],
        ]);

        $faker = new ColumnFaker($table, 'column');

        $this->assertEquals('null', $faker->fake());
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
    }

    private function assertFakerPropertyIsUsed(string $column, string $fakerProperty)
    {
        $this->assertNotNull(\Faker\Factory::create()->getFormatter($fakerProperty));

        $table = $this->mockTable('tablename', [
            $column => ['name' => $column],
        ]);

        $faker = new ColumnFaker($table, $column);

        $this->assertEquals("\$faker->$fakerProperty", $faker->fake());
    }

    /** @test */
    public function it_fakes_nulls_on_nullable_columns_sometimes()
    {
        $table = $this->mockTable('tablename', [
            'column' => ['required' => false],
        ]);

        $faker = new ColumnFaker($table, 'column');

        $this->assertEquals(
            '$faker->optional(0.9)->sentence',
            $faker->fake()
        );
    }

    /** @test */
    public function it_mixes_optional_columns_with_other_fakers()
    {
        $table = $this->mockTable('tablename', [
            'email' => ['required' => false],
            'column' => ['required' => false, 'type' => Type::DECIMAL],
        ]);

        $faker = new ColumnFaker($table, 'email');
        $this->assertEquals('$faker->optional(0.9)->email', $faker->fake());

        $faker = new ColumnFaker($table, 'column');
        $this->assertEquals('$faker->optional(0.9)->numerify(\'%##.##\')', $faker->fake());
    }

    /** @test */
    public function it_fakes_unique_values_on_columns_with_unique_indices()
    {
        $table = $this->mockTable('tablename', [
            'email' => ['unique' => true],
        ]);

        $faker = new ColumnFaker($table, 'email');

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

        $faker = new ColumnFaker($table, 'email');

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

        $faker = new ColumnFaker($table, 'user_id');

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

        $name = new ColumnFaker($table, 'name');
        $name->fake();
        $this->assertNull($name->referencedTable());

        $avatar = new ColumnFaker($table, 'avatar_id');
        $avatar->fake();
        $this->assertEquals('avatars', $avatar->referencedTable());

        $parent = new ColumnFaker($table, 'parent');
        $parent->fake();
        $this->assertEquals('users', $parent->referencedTable());
    }
}
