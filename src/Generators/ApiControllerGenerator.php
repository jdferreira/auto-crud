<?php

namespace Ferreira\AutoCrud\Generators;

class ApiControllerGenerator extends ControllerGenerator
{
    /**
     * Get the stub filename.
     */
    protected function stub(): string
    {
        return 'api_controller.php.stub';
    }

    /**
     * Get the output filename. The returned value is relative to the
     * application's base directory (usually `app/`).
     */
    protected function filename(): string
    {
        return app_path('Http/ApiControllers/' . $this->modelClass() . 'Controller.php');
    }

    /**
     * Get the replacements to use with the stub, based on these generator options.
     */
    protected function replacements(): array
    {
        return [
            'modelNamespace' => $this->modelNamespace(),
            'modelClass' => $this->modelClass(),
            'modelSingular' => $this->modelSingular(),
            'syncManyToManyRelationships' => $this->syncManyToManyRelationships(),
        ];
    }
}
