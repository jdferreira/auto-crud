<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ColumnFaker;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\FactoryGenerator;

class FactoryGeneratorTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param string $table
     * @param string $dir
     *
     * @return FactoryGenerator
     */
    private function generator(string $table): FactoryGenerator
    {
        return app(FactoryGenerator::class, [
            'table' => app(TableInformation::class, ['name' => $table]),
        ]);
    }

    /** @test */
    public function it_can_generate_a_factory()
    {
        $this->generator('users')->save();

        $this->assertFileExists(database_path('factories/UserFactory.php'));
    }

    /** @test */
    public function it_detects_model_namespace()
    {
        $code = $this->generator('users')->setModelDirectory('Models')->generate();

        $this->assertStringContainsString(
            'use App\Models\User;',
            $code
        );
    }

    /** @test */
    public function it_detects_referenced_models_qualified_name()
    {
        $code = $this->generator('products')->generate();
        $this->assertStringContainsString('use App\User;', $code);

        $code = $this->generator('products')->setModelDirectory('Models')->generate();
        $this->assertStringContainsString('use App\Models\User;', $code);
    }

    /** @test */
    public function it_defines_a_factory()
    {
        $code = $this->generator('products')->generate();

        $this->assertStringContainsString('use Faker\Generator as Faker;', $code);
        $this->assertStringContainsString('$factory->define(Product::class', $code);
    }

    /** @test */
    public function it_uses_column_definitions_to_decide_how_to_fake()
    {
        $this->app->bind(ColumnFaker::class, function () {
            return $this->mock(ColumnFaker::class, function ($mock) {
                $mock->shouldReceive('fake')->once();
                $mock->shouldReceive('referencedTable')->atMost()->times(1);
            });
        });

        $this->generator('users')->generate();
    }

    /** @test */
    public function it_generates_correct_factory_for_user()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains("
            return [
                'name' => \$faker->name,
                'email' => \$faker->randomFloat() <= 0.9 ? \$faker->unique()->email : null,
                'subscribed' => \$faker->boolean,
                'birthday' => \$faker->date,
                'wake_up' => \$faker->optional(0.9)->time,
            ];
        ", $code);
    }

    /** @test */
    public function it_generates_correct_factory_for_avatar()
    {
        $code = $this->generator('avatars')->generate();

        $this->assertCodeContains("
            return [
                'user_id' => function () {
                    return factory(User::class)->create()->id;
                },
                'file' => \$faker->file,
                'data' => \$faker->passthrough(random_bytes(1024)),
            ];
        ", $code);
    }

    /** @test */
    public function it_generates_correct_factory_for_product()
    {
        $code = $this->generator('products')->generate();

        $this->assertCodeContains("
            return [
                'owner_id' => function () {
                    return factory(User::class)->create()->id;
                },
                'type' => \$faker->optional(0.9)->randomElement(['food', 'stationery', 'other']),
                'value' => \$faker->numerify('###.##'),
                'start_at' => \$faker->dateTimeBetween('-10 years', 'now'),
            ];
        ", $code);
    }

    /** @test */
    public function it_generates_correct_factory_for_role()
    {
        $code = $this->generator('roles')->generate();

        $this->assertCodeContains("
            return [
                'name' => \$faker->name,
                'description' => \$faker->sentence,
            ];
        ", $code);
    }

    /** @test */
    public function it_generates_correct_factory_for_sale()
    {
        $code = $this->generator('sales')->generate();

        $this->assertCodeContains("
            return [
                'product_id' => function () {
                    return factory(Product::class)->create()->product_id;
                },
                'amount' => \$faker->numberBetween(0, 10000),
                'date' => \$faker->date,
            ];
        ", $code);
    }

    /** @test */
    public function it_creates_a_full_model_state_for_models_with_nullable_columns()
    {
        $code = $this->generator('users')->generate();
        $this->assertCodeContains("
            \$factory->state(User::class, 'full_model', function (Faker \$faker) {
                return [
                    'email' => \$faker->unique()->email,
                    'wake_up' => \$faker->time,
                ];
            });
        ", $code);

        $code = $this->generator('sales')->generate();
        $this->assertCodeContains("
            \$factory->state(Sale::class, 'full_model', function (Faker \$faker) {
                return [
                ];
            });
        ", $code);
    }

    /** @test */
    public function it_ignores_deleted_at_columns()
    {
        $code = $this->generator('products')->generate();
        $this->assertStringNotContainsString("'deleted_at' => ", $code);
    }
}
