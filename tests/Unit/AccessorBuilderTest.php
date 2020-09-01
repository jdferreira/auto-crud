<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\AccessorBuilder;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class AccessorBuilderTest extends TestCase
{
    /** @test */
    public function it_builds_labels_and_accessor()
    {
        $builder = new AccessorBuilder($this->mockTable('tablename', [
            'column' => [],
        ]), 'column');

        $this->assertNotNull($builder->label);
        $this->assertNotNull($builder->accessor);
    }

    /** @test */
    public function it_capitalizes_column_names()
    {
        $table = $this->mockTable('tablename', [
            'name' => [],
            'wants_email' => [],
        ]);

        $this->assertEquals('Name', (new AccessorBuilder($table, 'name'))->label);
        $this->assertEquals('Wants email', (new AccessorBuilder($table, 'wants_email'))->label);
    }

    /** @test */
    public function it_builds_accessors_usable_in_views()
    {
        $table = $this->mockTable('players', [
            'name' => [],
        ]);

        $builder = new AccessorBuilder($table, 'name');

        $this->assertEquals('{{ $player->name }}', $builder->accessor);
    }

    /** @test */
    public function it_crosses_relationships()
    {
        // This is a tricky test. We must mock the label column of the referred
        // table (in this case we say it is the `name` column), as well as it's
        // type (let's say it is string to facilitate the test). This test will
        // also ensure that the returned accessor is an HTML link to the `show`
        // route of the referred table. It also makes sure that the primary key
        // of the foreign table is correctly used in the `route` function.

        $table = $this->mockTable('players', [
            'team_id' => [
                'reference' => ['team', 'team_id'],
            ],
        ]);

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

        $builder = new AccessorBuilder($table, 'team_id');

        $this->assertEquals(
            '<a href="{{ route(\'team.show\', [\'team\' => $player->team_id]) }}">{{ $player->team->name }}</a>',
            $builder->accessor
        );
    }

    /** @test */
    public function it_builds_simple_accessors()
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

        $this->assertEquals('$player->name', (new AccessorBuilder($table, 'name'))->buildSimpleAccessor());
        $this->assertEquals('$player->team->name', (new AccessorBuilder($table, 'team_id'))->buildSimpleAccessor());
    }

    /** @test */
    public function it_chops_text_columns_to_30_characters()
    {
        $table = $this->mockTable('players', [
            'notes' => [
                'type' => Type::TEXT,
            ],
        ]);

        $this->assertEquals('{{ \Illuminate\Support\Str::limit($player->notes, 30) }}', (new AccessorBuilder($table, 'notes'))->accessor);
    }

    // TODO: I need many more tests here! See ViewIndexGeneratorTest for ideas!
}
