<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Ferreira\AutoCrud\Generators\MigrationGenerator;
use Ferreira\AutoCrud\Generators\MigrationSetGenerator;

class MigrationGeneratorTest extends TestCase
{
    /** @test */
    public function it_generates_valid_PHP_code()
    {
        $code = app(MigrationGenerator::class)->generate();

        $cmd = 'php -l';
        $specs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $specs, $pipes);

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        // Apparently we need to read the STDOUT pipe, or the process fails with a 255 code
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        $this->assertEquals(0, $exitCode, 'File contains syntax errors:' . PHP_EOL . $errors);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function it_generates_a_valid_migration_file()
    {
        $dir = '/tmp/xyz';

        @exec("rm -rf $dir");

        mkdir($dir);

        (new MigrationSetGenerator($dir))->save();

        Artisan::call('migrate', [
            '--path' => $dir,
            '--realpath' => true,
        ]);
    }
}
