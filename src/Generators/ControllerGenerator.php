<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Word;
use Ferreira\AutoCrud\Database\ManyToMany;

class ControllerGenerator extends TableBasedGenerator
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
            'syncManyToManyRelationships' => $this->syncManyToManyRelationships(),
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

    public function syncManyToManyRelationships()
    {
        return collect($this->db->manyToMany($this->table->name()))
            ->map(function (ManyToMany $relationship) {
                $model = Word::variableSingular($this->table->name());
                $method = Word::method($relationship->foreignTwo);
                $field = Word::kebab($relationship->foreignTwo);

                return "$model->$method()->sync(\$request->get('$field'));";
            })
            ->all();
    }
}
