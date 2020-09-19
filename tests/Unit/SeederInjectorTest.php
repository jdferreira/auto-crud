<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Injectors\SeederInjector;

class SeederInjectorTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    public function setUp(): void
    {
        parent::setUp();

        $this->files->delete(database_path('seeds/DatabaseSeeder.php'));
    }

    private function injector($tables): SeederInjector
    {
        return app(SeederInjector::class, [
            'tables' => $tables,
        ]);
    }

    /** @test */
    public function it_creates_the_main_seeder_file_if_necessary()
    {
        $this->injector(['users', 'avatars'])->inject();

        $this->assertFileExists(database_path('seeds/DatabaseSeeder.php'));
    }

    /** @test */
    public function it_does_not_create_the_main_seeder_file_if_not_necessary()
    {
        $this->files->put(
            database_path('seeds/DatabaseSeeder.php'),
            $this->files->get(__DIR__ . '/inputs/seeder.php')
        );

        $this->assertStringContainsString(
            'Make sure this line exists',
            $this->files->get(database_path('seeds/DatabaseSeeder.php'))
        );

        $this->injector(['users'])->inject();

        $this->assertStringContainsString(
            'Make sure this line exists',
            $this->files->get(database_path('seeds/DatabaseSeeder.php'))
        );
    }

    /** @test */
    public function it_appends_calls_to_seeder_classes_on_the_main_seeder()
    {
        $this->injector(['users', 'roles'])->inject();

        $seeder = $this->files->get(database_path('seeds/DatabaseSeeder.php'));

        $this->assertCodeContains('$this->call(UserSeeder::class);', $seeder);
        $this->assertCodeContains('$this->call(RoleSeeder::class);', $seeder);
    }

    /** @test */
    public function it_seeds_pivot_tables()
    {
        $this->injector(['users', 'roles'])->inject();

        $seeder = $this->files->get(database_path('seeds/DatabaseSeeder.php'));

        $this->assertCodeContains('$this->call(RoleUserSeeder::class);', $seeder);
    }

    /** @test */
    public function it_calls_seeders_in_the_correct_order()
    {
        $this->injector(['users', 'roles'])->inject();

        $seeder = $this->files->get(database_path('seeds/DatabaseSeeder.php'));

        $userCall = strpos($seeder, '$this->call(UserSeeder::class);');
        $roleCall = strpos($seeder, '$this->call(RoleSeeder::class);');
        $pivotCall = strpos($seeder, '$this->call(RoleUserSeeder::class);');

        $this->assertTrue($userCall < $pivotCall);
        $this->assertTrue($roleCall < $pivotCall);
    }
}
