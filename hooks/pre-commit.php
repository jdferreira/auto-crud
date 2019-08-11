#!/usr/bin/env php
<?php

// Let's save the non-staged files
exec('git stash save --keep-index');

// Run phpunit before committing and make sure all tests pass
exec('script --return --quiet -c "phpunit" /dev/null', $output, $code);

if ($code !== 0) {
    echo "\033[31m"; // Red text
    echo 'PHPUnit failed';
    echo "\033[0m"; // Reset
    echo ' - see output below' . PHP_EOL;

    echo PHP_EOL;
    echo implode(PHP_EOL, $output) . PHP_EOL;
    echo PHP_EOL;

    exec('git stash pop');

    exit(1);
}

// Get a list of files in the staging area
exec('git status --porcelain | egrep "^([AM]| M)" | cut -c4-', $staged);

$fixed = [];

foreach ($staged as $filename) {
    // Unescape escaped charaters
    if (preg_match('/^".*"$/', $filename)) {
        $unescaped = stripcslashes(substr($filename, 1, -1));
    } else {
        $unescaped = $filename;
    }

    // is_file - to avoid problems with "renamed" and "deleted" files.
    if (preg_match('/\.php$/', $unescaped) && is_file($unescaped)) {
        $output = [];

        exec(sprintf('php-cs-fixer fix "%s" 2>/dev/null', $unescaped), $output);

        if (count($output) > 0) {
            // Any output means that fixes were applied to this file,
            // in which case add the fixed file back to the staging area.
            exec(sprintf('git add "%s"', $unescaped));

            $fixed[] = $filename;
        }
    }
}

if (count($fixed) > 0) {
    echo "\033[32m"; // Green text
    echo 'Code style fixes were applied to the following files:';
    echo "\033[m";
    echo PHP_EOL . PHP_EOL;

    $counter = 1;
    foreach ($fixed as $filename) {
        echo '  ' . $counter . ') ' . $filename . PHP_EOL;
        $counter++;
    }
}

// Allow commit by returing with an exist code of 0
exec('git stash pop');

exit(0);
