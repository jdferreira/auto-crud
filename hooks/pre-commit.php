#!/usr/bin/env php
<?php

// Let's save the non-staged code into stash `precommit-verification`
exec(
    'git stash push -m precommit-verification --keep-index --include-untracked'
);

// If the code is not properly formatted, stop the commit process. In that case,
// let the user know and pop the stash.
exec('php-cs-fixer fix --dry-run >/dev/null 2>&1', $_, $exitCode);

if ($exitCode !== 0) {
    echo "\033[32m"; // Green text
    echo 'Code style fixes are required';
    echo "\033[m";
    echo PHP_EOL;

    echo 'Use php-cs-fixer fix to fix them' . PHP_EOL;
    echo '(No automatic format is done to prevent merge conflicts with the files that contain unstaged code)' . PHP_EOL;

    exec('git stash pop $(git stash list | grep precommit-verification | cut -d: -f1 | head -n 1)');

    exit(1);
}

// Run phpunit before committing and make sure all tests pass
$specs = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$pupunitProcess = proc_open('phpunit --exclude-group end-to-end', $specs, $pipes);

stream_copy_to_stream($pipes[1], STDOUT);
fclose($pipes[1]);

stream_copy_to_stream($pipes[2], STDERR);
fclose($pipes[2]);

if (proc_close($pupunitProcess) !== 0) {
    echo "\033[31m"; // Red text
    echo 'PHPUnit failed';
    echo "\033[m";
    echo PHP_EOL;

    exec('git stash pop $(git stash list | grep precommit-verification | cut -d: -f1 | head -n 1)');

    exit(1);
}

exit(0);
