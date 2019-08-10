<?php

require __DIR__ . '/vendor/autoload.php';

return (new MattAllan\LaravelCodeStyle\Config())
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude('laradock')
    )
    ->setRules([
        '@Laravel' => true,
        'concat_space' => ['spacing' => 'one'],
    ]);
