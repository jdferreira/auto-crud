<?php

namespace Ferreira\AutoCrud;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterSuccessfulTestHook;

class TestDurationHook implements AfterSuccessfulTestHook, AfterLastTestHook
{
    private $times = [];

    public function executeAfterSuccessfulTest(string $test, float $time): void
    {
        $this->times[$test] = $time;
    }

    public function executeAfterLastTest(): void
    {
        if (env('PRINT_TIMES', false)) {
            arsort($this->times);

            $slice = array_slice($this->times, 0, 10);

            $count = count($slice);
            $title = 'Execution times';

            if ($count < count($this->times)) {
                $title .= " (longest $count tests)";
            }

            echo "\n\n\033[32m$title:\033[m\n";

            // Print execution times of the 10 longest tests
            foreach ($slice as $test => $time) {
                printf(' - %.3f seconds for %s' . PHP_EOL, $time, $test);
            }
        }
    }
}
