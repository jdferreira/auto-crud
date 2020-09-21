<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Word;

class ControllerGenerator extends BaseGenerator
{
    /**
     * Get the stub filename.
     */
    protected function stub(): string
    {
        return 'controller.php.stub';
    }

    /**
     * Get the output filename. The returned value is relative to the
     * application's base directory (usually `app/`).
     */
    protected function filename(): string
    {
        return app_path('Http/Controllers/' . $this->modelClass() . 'Controller.php');
    }

    /**
     * Get the replacements to use with the stub, based on these generator options.
     */
    protected function replacements(): array
    {
        return [
            'tablename' => $this->table->name(),
            'modelNamespace' => $this->modelNamespace(),
            'modelClass' => $this->modelClass(),
            'modelSingular' => $this->modelSingular(),
            'modelPlural' => $this->modelPlural(),
            'modelSingularNoDollar' => $this->modelSingularNoDollar(),
        ];
    }

    protected function modelSingular()
    {
        return Word::variableSingular($this->table->name());
    }

    protected function modelPlural()
    {
        return Word::variable($this->table->name(), false);
    }

    protected function modelSingularNoDollar()
    {
        return Word::variableSingular($this->table->name(), false);
    }
}
