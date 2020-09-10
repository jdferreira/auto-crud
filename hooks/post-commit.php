#!/usr/bin/env php
<?php

// Because our pre-commit created a stash, we need to pop it now
// However, only do this if there is really something to pop
exec('git stash list | grep precommit-verification | cut -d: -f1 | head -n 1', $stashes);

if (count($stashes) === 1) {
    exec("git stash pop {$stashes[0]}");
}
