<?php

namespace Ferreira\AutoCrud\Generators;

class LayoutViewGenerator extends PhpGenerator
{
    public function stub(): string
    {
        return 'layout.php.stub';
    }

    public function replacements(): array
    {
        return [];
    }

    public function filename(): string
    {
        return base_path('resources/views/layouts/app.blade.php');
    }
}
