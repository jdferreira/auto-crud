<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ColumnFaker;
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
            'table' => $table,
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
        $code = $this->generator('users')->setModelDirectory('Models')->save();

        $this->assertContains(
            'use App\Models\User;',
            $this->files->get(database_path('factories/UserFactory.php'))
        );
    }

    /** @test */
    public function it_defines_a_factory()
    {
        $code = $this->generator('products')->generate();

        $this->assertContains('use Faker\Generator as Faker;', $code);
        $this->assertContains('$factory->define(Product::class', $code);
    }

    /** @test */
    public function it_uses_column_definitions_to_decide_how_to_fake()
    {
        $this->app->bind(ColumnFaker::class, function () {
            return $this->mock(ColumnFaker::class, function ($mock) {
                $mock->shouldReceive('fake')->once();
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
                'type' => \$faker->optional(0.9)->sentence,
                'value' => \$faker->numerify('###.##'),
                'start_at' => \$faker->dateTimeBetween('-10 years', 'now'),
                'deleted_at' => \$faker->optional(0.9)->dateTimeBetween('-10 years', 'now'),
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
}
