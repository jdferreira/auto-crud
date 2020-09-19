<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Word;
use Illuminate\Support\Str;
use Ferreira\AutoCrud\AccessorBuilder;

class ViewShowGenerator extends BaseGenerator
{
    /**
     * Return the filename containing the stub this generator is based on.
     *
     * @return string
     */
    protected function stub(): string
    {
        return 'view.show.php.stub';
    }

    /**
     * Return the output filename where this file will be saved to.
     *
     * @return string
     */
    protected function filename(): string
    {
        return resource_path(
            'views/' . $this->table->name() . '/show.blade.php'
        );
    }

    /**
     * Return the stub replacements used with the stub.
     *
     * @return array
     */
    protected function replacements(): array
    {
        return [
            'modelSingularCapitalized' => $this->modelSingularCapitalized(),
            'values' => $this->values(),
            'buttons' => $this->buttons(),
        ];
    }

    protected function modelSingularCapitalized()
    {
        return Word::labelUpperSingular($this->table->name());
    }

    private function values()
    {
        $result = [];

        foreach ($this->visibleColumns() as $column) {
            $builder = app(AccessorBuilder::class, ['table' => $this->table]);

            $label = $builder->label($column);
            $accessor = $builder->viewAccessor($column);

            $result[] = "<tr><th>$label</th><td>$accessor</td></tr>";
        }

        return $result;
    }

    protected function visibleColumns()
    {
        return collect($this->table->columns())->filter(function ($column) {
            return ! in_array($column, ['created_at', 'updated_at', 'deleted_at']);
        });
    }

    private function buttons()
    {
        $tablename = $this->table->name();
        $routeParam = Word::snakeSingular($tablename);
        $model = Word::variableSingular($tablename);

        return [
            "<a href=\"{{ route('$tablename.edit', ['$routeParam' => $model]) }}\">Edit</a>",
            "<form action=\"{{ route('$tablename.destroy', ['$routeParam' => $model]) }}\" method=\"POST\">",
            '    @method(\'DELETE\')',
            '    @csrf',
            '    <button type="submit">Delete</button>',
            '</form>',
        ];
    }
}
