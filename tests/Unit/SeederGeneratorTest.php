<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\SeederGenerator;

class SeederGeneratorTest extends TestCase
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
     * @return SeederGenerator
     */
    private function generator(string $table): SeederGenerator
    {
        return app(SeederGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_seeder()
    {
        $this->generator('users')->save();

        $this->assertFileExists(database_path('seeds/UserSeeder.php'));
    }

    /** @test */
    public function it_detects_model_namespace()
    {
        $this->assertContains(
            'use App\User;',
            $this->generator('users')->generate()
        );

        $this->assertContains(
            'use App\Models\User;',
            $this->generator('users')->setModelDirectory('Models')->generate()
        );
    }

    /** @test */
    public function it_defines_a_seeder()
    {
        $code = $this->generator('products')->generate();

        $this->assertRegExp('/factory\(Product::class(,\s*\d+)?\)->create\(\);/', $code);
    }

    /** @test */
    public function it_seeds_pivot_tables()
    {
        $code = $this->generator('role_user')->generate();

        $this->assertContains(
            'use Ferreira\AutoCrud\PivotSeeder;',
            $code
        );

        $this->assertContains(
            "app(PivotSeeder::class)->seed('role_user');",
            $code
        );

        $this->assertNotContains(
            'use App\RoleUser;',
            $code
        );
    }
}
