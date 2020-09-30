<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Illuminate\Support\Str;
use Ferreira\AutoCrud\AccessorBuilder;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ModelGenerator;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class AccessorBuilderTest extends TestCase
{
    private function builder(TableInformation $table): AccessorBuilder
    {
        return app(AccessorBuilder::class, ['table' => $table]);
    }

    /**
     * Mocks the `DatabaseInformation` such that we can specify the label column
     * of any specific table. Note that only the last call to this method takes
     * effect, as it effectively rebind the DatabaseInformation instance in the
     * application.
     *
     * @param string $tablename
     * @param string|null $column
     */
    private function mockLabelColumn(string $tablename, ?string $column)
    {
        $this->app->bind(DatabaseInformation::class, function () use ($tablename, $column) {
            return $this->mock(DatabaseInformation::class, function ($mock) use ($tablename, $column) {
                $mock->shouldReceive('table')->with($tablename)->andReturn(
                    $this->mock(TableInformation::class, function ($mock) use ($column) {
                        $mock->shouldReceive('labelColumn')->with()->andReturn($column);
                    })
                );
            });
        });
    }

    /** @test */
    public function it_builds_labels_and_accessor()
    {
        $builder = $this->builder($this->mockTable('tablename', [
            'column' => [],
        ]));

        $this->assertNotNull($builder->label('column'));
        $this->assertNotNull($builder->simpleAccessor('column'));
        $this->assertNotNull($builder->viewAccessor('column'));
    }

    /** @test */
    public function it_capitalizes_labels()
    {
        $builder = $this->builder($this->mockTable('tablename', [
            'name' => [],
            'wants_email' => [],
        ]));

        $this->assertEquals('Name', $builder->label('name'));
        $this->assertEquals('Wants email', $builder->label('wants_email'));
    }

    /** @test */
    public function it_builds_simple_accessors()
    {
        $builder = $this->builder($this->mockTable('players', [
            'name' => [],
        ]));

        $this->assertEquals('$player->name', $builder->simpleAccessor('name'));
    }

    /** @test */
    public function it_uses_the_label_column_of_the_foreign_table_on_simple_accessors_that_refer_to_other_tables()
    {
        $table = $this->mockTable('players', [
            'team_id' => [
                'reference' => ['teams', 'id'],
            ],
        ]);

        $this->mockLabelColumn('teams', 'name');

        $builder = $this->builder($table);

        $this->assertEquals('$player->team->name', $builder->simpleAccessor('team_id'));
    }

    /** @test */
    public function it_detects_foreign_tables_without_a_label_column()
    {
        $table = $this->mockTable('players', [
            'shirt_id' => [
                'reference' => ['shirts', 'id'],
            ],
        ]);

        $this->mockLabelColumn('shirts', null);

        $builder = $this->builder($table);

        $this->assertEquals('$player->shirt_id', $builder->simpleAccessor('shirt_id'));
    }

    /** @test */
    public function it_can_chain_simple_accessor_onto_existing_code()
    {
        $table = $this->mockTable('players', [
            'name' => [],
            'team_id' => [
                'reference' => ['team', 'team_id'],
            ],
        ]);

        // Again let's put the app in such a mocked state that allows us to unit
        // test the AccessorBuilder class

        $this->app->bind(DatabaseInformation::class, function () {
            return $this->mock(DatabaseInformation::class, function ($mock) {
                $mock->shouldReceive('table')->with('team')->andReturn(
                    $this->mock(TableInformation::class, function ($mock) {
                        $mock->shouldReceive('labelColumn')->with()->andReturn('name');
                        $mock->shouldReceive('type')->with('name')->andReturn(Type::STRING);
                    })
                );
            });
        });

        $builder = $this->builder($table);

        $this->assertEquals(
            'factory(Player::class)->create()->name',
            $builder->simpleAccessor('name', 'factory(Player::class)->create()')
        );

        $this->assertEquals(
            'factory(Player::class)->create()->team->name',
            $builder->simpleAccessor('team_id', 'factory(Player::class)->create()')
        );
    }

    /** @test */
    public function it_builds_accessors_usable_in_views()
    {
        $builder = $this->builder($this->mockTable('players', [
            'name' => [],
        ]));

        $this->assertEquals('{{ $player->name }}', $builder->viewAccessor('name'));
    }

    /** @test */
    public function it_formats_date_accessors_in_views()
    {
        $builder = $this->builder($this->mockTable('players', [
            'date' => ['type' => Type::DATE],
            'time' => ['type' => Type::TIME],
            'when' => ['type' => Type::DATETIME],
        ]));

        $this->assertEquals("{{ \$player->date->format('Y-m-d') }}", $builder->viewAccessor('date'));
        $this->assertEquals('{{ $player->time }}', $builder->viewAccessor('time'));
        $this->assertEquals("{{ \$player->when->format('Y-m-d H:i:s') }}", $builder->viewAccessor('when'));
    }

    /** @test */
    public function it_formats_boolean_values()
    {
        $builder = $this->builder($this->mockTable('players', [
            'is_good' => ['type' => Type::BOOLEAN],
        ]));

        $this->assertEquals("{{ \$player->is_good ? '&#10004;' : '&#10008;' }}", $builder->viewAccessor('is_good'));
    }

    /** @test */
    public function it_formats_nullable_boolean_values()
    {
        $builder = $this->builder($this->mockTable('players', [
            'is_good' => [
                'type' => Type::BOOLEAN,
                'required' => false,
            ],
        ]));

        $this->assertEquals(
            "{{ \$player->is_good !== null ? (\$player->is_good ? '&#10004;' : '&#10008;') : null }}",
            $builder->viewAccessor('is_good')
        );
    }

    /** @test */
    public function it_formats_only_when_the_value_is_not_null()
    {
        $builder = $this->builder($this->mockTable('players', [
            'date' => [
                'type' => Type::DATE,
                'required' => false,
            ],
            'motto' => [
                'required' => false,
            ],
        ]));

        $this->assertEquals(
            "{{ \$player->date !== null ? \$player->date->format('Y-m-d') : null }}",
            $builder->viewAccessor('date')
        );

        $this->assertEquals(
            '{{ $player->motto }}',
            $builder->viewAccessor('motto')
        );
    }

    /** @test */
    public function it_chops_text_columns_to_30_characters_in_views()
    {
        $builder = $this->builder($this->mockTable('players', [
            'notes' => [
                'type' => Type::TEXT,
            ],
        ]));

        $this->assertEquals(
            '{{ \Illuminate\Support\Str::limit($player->notes, 30) }}',
            $builder->viewAccessor('notes')
        );
    }

    /** @test */
    public function it_builds_view_accessors_that_cross_relationships_as_links()
    {
        $table = $this->mockTable('players', [
            'team_id' => [
                'reference' => ['teams', 'id'],
            ],
        ]);

        $this->app->bind(DatabaseInformation::class, function () {
            return $this->mock(DatabaseInformation::class, function ($mock) {
                $mock->shouldReceive('table')->with('teams')->andReturn(
                    $this->mock(TableInformation::class, function ($mock) {
                        $mock->shouldReceive('labelColumn')->with()->andReturn('name');
                    })
                );
            });
        });

        $builder = $this->builder($table);

        $this->assertEquals(
            '<a href="{{ route(\'teams.show\', [\'team\' => $player->team_id]) }}">{{ $player->team->name }}</a>',
            $builder->viewAccessor('team_id')
        );
    }

    /** @test */
    public function it_builds_view_accessors_that_cross_relationships_even_when_the_foreign_table_does_not_have_a_label_column()
    {
        $table = $this->mockTable('players', [
            'team_id' => [
                'reference' => ['teams', 'id'],
            ],
        ]);

        $this->mockLabelColumn('teams', null);

        $builder = $this->builder($table);

        $this->assertEquals(
            '<a href="{{ route(\'teams.show\', [\'team\' => $player->team_id]) }}">Team #{{ $player->team_id }}</a>',
            $builder->viewAccessor('team_id')
        );
    }

    /** @test */
    public function it_snake_cases_route_parameters()
    {
        $table = $this->mockTable('students', [
            'school_id' => [
                'reference' => ['magical_schools', 'id'],
            ],
        ]);

        $this->mockLabelColumn('magical_schools', null);

        $builder = $this->builder($table);

        $this->assertEquals(
            '<a href="{{ route(\'magical_schools.show\', [\'magical_school\' => $student->school_id]) }}">Magical school #{{ $student->school_id }}</a>',
            $builder->viewAccessor('school_id')
        );
    }

    /** @test */
    public function it_treats_sql_keywords_as_general_words()
    {
        $builder = $this->builder($this->mockTable('players', [
            'primary' => [],
        ]));

        $this->assertEquals('Primary', $builder->label('primary'));
        $this->assertEquals('$player->primary', $builder->simpleAccessor('primary'));
        $this->assertEquals('{{ $player->primary }}', $builder->viewAccessor('primary'));
    }

    /** @test */
    public function it_does_not_cast_simple_accessors()
    {
        $builder = $this->builder($this->mockTable('players', [
            'birthday' => [
                'type' => Type::DATE,
            ],
        ]));

        $this->assertEquals(
            '$player->birthday',
            $builder->simpleAccessor('birthday')
        );
    }

    /** @test */
    public function it_casts_view_accessors_according_to_column_type()
    {
        $builder = $this->builder($this->mockTable('players', [
            'birthday' => [
                'type' => Type::DATE,
            ],
        ]));

        $this->assertEquals(
            "{{ \$player->birthday->format('Y-m-d') }}",
            $builder->viewAccessor('birthday')
        );
    }

    /** @test */
    public function it_can_format_simple_accessors()
    {
        $builder = $this->builder($this->mockTable('players', [
            'date' => ['type' => Type::DATE],
            'time' => ['type' => Type::TIME],
            'when' => ['type' => Type::DATETIME],
            'is_good' => ['type' => Type::BOOLEAN],
        ]));

        $this->assertEquals("\$player->date->format('Y-m-d')", $builder->simpleAccessorFormatted('date'));
        $this->assertEquals('$player->time', $builder->simpleAccessorFormatted('time'));
        $this->assertEquals("\$player->when->format('Y-m-d H:i:s')", $builder->simpleAccessorFormatted('when'));
        $this->assertEquals("\$player->is_good ? '&#10004;' : '&#10008;'", $builder->simpleAccessorFormatted('is_good'));
    }

    /** @test */
    public function model_relationships_and_simple_accessors_are_always_in_sync()
    {
        $students = $this->mockTable('students', [
            'id' => ['primaryKey' => true],
            'name' => [],
        ]);
        $pets = $this->mockTable('pets', [
            'human_owner' => ['reference' => ['students', 'id']],
        ]);
        $this->mockDatabase($students, $pets);

        $builder = $this->builder($pets);
        $accessor = $builder->simpleAccessor('human_owner');
        $expectedRelationshipName = Str::between($accessor, '$pet->', '->name');

        $petsGenerator = app(ModelGenerator::class, ['table' => $pets]);
        $code = $petsGenerator->generate();
        $this->assertCodeContains("
            public function $expectedRelationshipName()
            {
                return \$this->belongsTo(Student::class, 'human_owner');
            }
        ", $code);
    }

    /** @test */
    public function it_labels_id_columns_in_all_upper_case()
    {
        $builder = $this->builder($this->mockTable('students', [
            'id' => [],
        ]));

        $this->assertEquals('ID', $builder->label('id'));
    }
}
