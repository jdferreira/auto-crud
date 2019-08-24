<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Str;
use Ferreira\AutoCrud\PivotSeeder;
use Illuminate\Support\Facades\DB;

class PivotSeederTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    private function seedTables(array $tables)
    {
        // In here, we want to mimick the db:seed command. Since we are testing
        // with orchestra, we do not have a full laravel app, and no
        // DatabaseSeeder class either. So, let's mimick its behaviour now.
        // Also, the seeder classes are not loaded automatically, as they live
        // in the root namespace and we would have to call `composer
        // dump-autoload` to ensure laravel finds them. So, let's require the
        // classes here.
        foreach ($tables as $table) {
            $seeder = Str::studly(Str::singular($table)) . 'Seeder';

            require_once database_path("seeds/$seeder.php");

            $this->seed($seeder);
        }
    }

    /** @test */
    public function this_test_case_can_seed_regular_tables()
    {
        $this->artisan('autocrud:model', ['--table' => ['users', 'roles']]);
        $this->artisan('autocrud:factory', ['--table' => ['users', 'roles']]);
        $this->artisan('autocrud:seeder', ['--table' => ['users', 'roles']]);

        $this->seedTables(['users', 'roles']);

        $this->assertEquals(50, DB::table('users')->count());
        $this->assertEquals(50, DB::table('roles')->count());
    }

    /** @test */
    public function it_seeds_pivot_tables()
    {
        $this->artisan('autocrud:model', ['--table' => ['users', 'roles']]);
        $this->artisan('autocrud:factory', ['--table' => ['users', 'roles']]);
        $this->artisan('autocrud:seeder', ['--table' => ['users', 'roles']]);

        $this->seedTables(['users', 'roles']);

        app(PivotSeeder::class)->seed('role_user');

        $this->assertEquals(50, DB::table('role_user')->count());
    }
}
