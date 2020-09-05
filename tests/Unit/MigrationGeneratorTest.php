<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\MigrationGenerator;
use Tests\MigrationSetGenerator;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\ExpectationFailedException;

class MigrationGeneratorTest extends TestCase
{
    /** @test */
    public function it_generates_valid_PHP_code()
    {
        $code = (new MigrationGenerator())->code();

        $cmd = 'php -l';
        $specs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $specs, $pipes);

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        try {
            $this->assertEquals(0, $exitCode, 'File contains syntax errors:' . PHP_EOL . $errors);
        } catch (ExpectationFailedException $e) {
            dump($code);

            throw $e;
        }
    }

    /** @test */
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

        $this->assertTrue(true); // If we reached this, we have successfully ran the test
    }
}
