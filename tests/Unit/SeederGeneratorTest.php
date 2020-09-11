<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Database\TableInformation;
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
            'table' => app(TableInformation::class, ['name' => $table]),
        ]);
    }

    /** @test */
    public function it_can_generate_a_seeder()
    {
        $this->generator('users')->save();

        $this->assertFileExists(database_path('seeds/UserSeeder.php'));
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

        $this->assertStringContainsString(
            'use Ferreira\AutoCrud\PivotSeeder;',
            $code
        );

        $this->assertStringContainsString(
            "app(PivotSeeder::class)->seed('role_user');",
            $code
        );

        $this->assertStringNotContainsString(
            'use App\RoleUser;',
            $code
        );
    }
}
