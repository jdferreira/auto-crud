<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\VersionChecker;
use Ferreira\AutoCrud\Generators\ColumnFaker;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\FactoryGenerator;

class FactoryGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return FactoryGenerator
     */
    private function generator($table): FactoryGenerator
    {
        // Bind the generator we want to test (because the actual generator
        // class may be a different one, since we can be running on laravel < 8)
        app(VersionChecker::class)->mockVersion('8.0.0');
        app()->bind(FactoryGenerator::class);

        return app(FactoryGenerator::class, ['table' => $table]);
    }

    /** @test */
    public function it_can_generate_a_factory()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(database_path('factories/StudentFactory.php'));
    }

    /** @test */
    public function it_detects_referenced_models_qualified_name()
    {
        $generator = $this->generator(
            $this->mockTable('students')
        );

        $code = $generator->generate();
        $this->assertStringContainsString('use App\Student;', $code);

        $code = $generator->setModelDirectory('Models')->generate();
        $this->assertStringContainsString('use App\Models\Student;', $code);
    }

    /** @test */
    public function it_defines_a_factory()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains('
            use Illuminate\Database\Eloquent\Factories\Factory;
        ', $code);

        $this->assertCodeContains('
            class StudentFactory extends Factory
        ', $code);
    }

    /** @test */
    public function it_uses_column_definitions_to_decide_how_to_fake()
    {
        $this->app->bind(ColumnFaker::class, function ($param) {
            return $this->mock(ColumnFaker::class, function ($mock) {
                $mock->shouldReceive('fake')->once();
                $mock->shouldReceive('referencedTable')->atMost()->times(1);
            });
        });

        // We need to have at least one column to ensure that the ColumnFaker
        // class is instantiated at least once.
        $table = $this->mockTable('students', [
            'name' => [],
        ]);

        $this->generator($table)->generate();
    }

    /** @test */
    public function it_creates_a_full_model_state_for_models_with_nullable_columns()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'pet' => [
                    'type' => Type::STRING,
                    'required' => false,
                ],
            ])
        )->generate();

        $this->assertCodeContains('
            public function full()
            {
                return $this->state(function (array $attributes) {
                    return [
                        \'pet\' => $this->faker->sentence,
                    ];
                });
            }
        ', $code);
    }

    /** @test */
    public function full_model_states_are_transitive()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'pet_id' => [
                    'reference' => ['pets', 'id'],
                    'required' => false,
                ],
            ])
        )->generate();

        $this->assertCodeContains(
                '
            \'pet_id\' => function () {
                return Pet::factory()->full()->create()->id;
            },
        ',
            $code
        );
    }

    /** @test */
    public function it_ignores_deleted_at_columns()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'deleted_at' => [
                    'type' => Type::DATETIME,
                    'required' => false,
                ],
            ])
        )->generate();

        $this->assertStringNotContainsString("'deleted_at' => ", $code);
    }
}
