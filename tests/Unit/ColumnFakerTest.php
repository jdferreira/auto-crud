<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Doctrine\DBAL\Types\Type;
use Ferreira\AutoCrud\EnumType;
use Doctrine\DBAL\Schema\Column;
use Ferreira\AutoCrud\Generators\ColumnFaker;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class ColumnFakerTest extends TestCase
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
        ];

        if (is_string($options['type'])) {
            $options['type'] = Type::getType($options['type']);
        }

        return $this->mock(Column::class, function ($mock) use ($options) {
            $mock->shouldReceive('getAutoincrement')->andReturn($options['autoincrement']);
            $mock->shouldReceive('getName')->andReturn($options['name']);
            $mock->shouldReceive('getType')->andReturn($options['type']);
            $mock->shouldReceive('getNotnull')->andReturn($options['required']);
        });
    }

    private function assertFakesRegularDatabaseType(string $type, string $fake)
    {
        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn(['type' => $type])
        );

        $this->assertEquals("\$faker->$fake", $faker->fake());
    }

    private function assertFakerPropertyIsUsed(string $columnName, string $fakerProperty)
    {
        $this->assertNotNull(\Faker\Factory::create()->getFormatter($fakerProperty));

        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn(['name' => $columnName])
        );

        $this->assertEquals("\$faker->$fakerProperty", $faker->fake());
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
        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn()
        );

        $this->assertInstanceOf(
            ColumnFaker::class,
            $faker
        );
    }

    /** @test */
    public function it_ignores_autoincrement_columns()
    {
        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn(['autoincrement' => true])
        );

        $this->assertEquals('', $faker->fake());
    }

    /** @test */
    public function it_ignores_timestamp_columns()
    {
        foreach (['created_at', 'updated_at'] as $name) {
            $faker = new ColumnFaker(
                'tablename',
                $this->mockColumn(['name' => $name, 'type' => Type::DATETIME])
            );

            $this->assertEquals('', $faker->fake());
        }
    }

    /** @test */
    public function it_fakes_regular_database_types()
    {
        $fakes = [
            // Integers
            Type::BIGINT => 'numberBetween(10000, 100000)',
            Type::INTEGER => 'numberBetween(0, 10000)',
            Type::SMALLINT => 'numberBetween(0, 1000)',

            // Boolean
            Type::BOOLEAN => 'boolean',

            // Date, time and related
            Type::DATETIME => 'dateTimeBetween(\'-10 years\', \'now\')',
            Type::DATETIME_IMMUTABLE => 'dateTimeBetween(\'-10 years\', \'now\')',
            Type::DATETIMETZ => 'dateTimeBetween(\'-10 years\', \'now\', new DateTimeZone(\'UTC\'))',
            Type::DATETIMETZ_IMMUTABLE => 'dateTimeBetween(\'-10 years\', \'now\', new DateTimeZone(\'UTC\'))',

            Type::DATE => 'date',
            Type::DATE_IMMUTABLE => 'date',

            Type::TIME => 'time',
            Type::TIME_IMMUTABLE => 'time',

            // Floats
            Type::FLOAT => 'randomFloat',
            Type::DECIMAL => 'numerify(\'###.##\')',

            // Text
            Type::STRING => 'sentence',
            Type::TEXT => 'text',

            // Other
            Type::GUID => 'uuid',

            // Raw data
            Type::BINARY => 'passthrough(random_bytes(1024))',
            Type::BLOB => 'passthrough(random_bytes(1024))',

            // TODO: This test is missing the following database type, which I am not sure how to handle.
            //   - Type::TARRAY
            //   - Type::SIMPLE_ARRAY
            //   - Type::JSON_ARRAY
            //   - Type::JSON
            //   - Type::DATEINTERVAL
            //   - Type::OBJECT
        ];

        foreach ($fakes as $type => $fake) {
            $this->assertFakesRegularDatabaseType($type, $fake);
        }
    }

    /** @test */
    public function it_defaults_to_the_string_null()
    {
        // Mock a column with a type that is not recognized, by mocking its type as well
        $column = $this->mockColumn([]);

        $faker = new ColumnFaker('tablename', $column);

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

    /** @test */
    public function it_fakes_nulls_on_nullable_columns_sometimes()
    {
        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn(['required' => false, 'name' => 'email'])
        );

        $this->assertEquals(
            '$faker->optional(0.9)->email',
            $faker->fake()
        );
    }

    /** @test */
    public function it_fakes_unique_values_on_columns_with_unique_indices()
    {
        $this->makeColumnsUnique();

        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn(['name' => 'email'])
        );

        $this->assertEquals(
            '$faker->unique()->email',
            $faker->fake()
        );
    }

    /** @test */
    public function it_fakes_unique_nullable_columns()
    {
        $this->makeColumnsUnique();

        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn(['required' => false, 'name' => 'email'])
        );

        $this->assertEquals(
            '$faker->randomFloat() <= 0.9 ? $faker->unique()->email : null',
            $faker->fake()
        );
    }

    /** @test */
    public function it_creates_a_model_for_foreign_keys()
    {
        $this->mock(DatabaseInformation::class, function ($mock) {
            $mock
                ->shouldReceive('foreignKeysReferences')
                ->with('products', 'owner_id')
                ->andReturn(['users', 'id']);
            $mock->shouldReceive('unique')->andReturn(false);
        });

        $faker = new ColumnFaker(
            'products',
            $this->mockColumn(['name' => 'owner_id'])
        );

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
    public function it_fakes_enum_types()
    {
        $faker = new ColumnFaker(
            'tablename',
            $this->mockColumn([
                'type' => EnumType::generateDynamicEnumType('tablename', 'enum_column', ['one', 'two']),
            ])
        );

        $this->assertEquals(
            '$faker->randomElement([\'one\', \'two\'])',
            $faker->fake()
        );
    }

    /*

    /** @test * /
    public function it_can_register_column_faker_closures()
    {
        $faker = new ColumnFaker();

        $faker->register(function (Column $column) {
            return 'fake';
        });

        $this->assertEquals(
            'fake',
            $faker->fake($this->mock(Column::class))
        );
    }

    /** @test * /
    public function precedence_of_registered_closures_is_last_one_first()
    {
        $faker = new ColumnFaker();

        $faker->register(function (Column $column) {
            return 'first';
        });

        $faker->register(function (Column $column) {
            return 'second';
        });

        $this->assertEquals(
            'second',
            $faker->fake($this->mock(Column::class))
        );
    }

    /** @test * /
    public function registered_closures_can_return_null_on_unrecognized_columns()
    {
        $faker = new ColumnFaker();

        $column = $this->mock(Column::class, function ($mock) {
            $mock->shouldReceive('getName')->andReturn('first');
        });

        $faker->register(function (Column $column) {
            if ($column->getName() === 'first') {
                return 'first';
            }
        });

        $faker->register(function (Column $column) {
            if ($column->getName() === 'second') {
                return 'second';
            }
        });

        $this->assertEquals('first', $faker->fake($column));
    }

    /** @test * /
    public function it_defaults_to_regular_fake_when_column_misses_all_registered_fakers()
    {
        $faker = new ColumnFaker();

        $faker->register(function (Column $column) {
            if ($column->getName() === 'name') {
                return '$faker->name';
            }
        });

        $faker->register(function (Column $column) {
            if ($column->getType() === Type::getType(Type::INTEGER)) {
                return '$faker->randomDigit';
            }
        });

        $this->assertEquals(
            '$faker->sentence',
            $faker->fake(new Column('description', Type::getType(Type::STRING)))
        );
    }

    /** @test * /
    public function when_closure_returns_false_the_column_does_not_have_a_faker()
    {
        $local = '';

        $this->faker->register(function (Column $column) use (&$local) {
            $local = 'should not run';
        });

        $faker->register(function (Column $column) {
            if ($column->getName() === 'id') {
                return ColumnFaker::IGNORE;
            }
        });

        $column = $this->mock(Column::class, function ($mock) {
            $mock->shouldReceive('getName')->andReturn('id');
        });

        $this->assertEquals(false, $faker->fake($column));
        $this->assertEquals('', $local);
    }

    */
}
