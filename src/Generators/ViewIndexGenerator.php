<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Word;
use Ferreira\AutoCrud\AccessorBuilder;

class ViewIndexGenerator extends TableBasedGenerator
{
    /**
     * Return the filename containing the stub this generator is based on.
     *
     * @return string
     */
    protected function stub(): string
    {
        return 'view.index.php.stub';
    }

    /**
     * Return the output filename where this file will be saved to.
     *
     * @return string
     */
    protected function filename(): string
    {
        return resource_path(
            // 'views/' . Str::snake(Str::plural($this->table->name())) . '/index.blade.php'
            'views/' . $this->table->name() . '/index.blade.php'
        );
    }

    /**
     * Return the stub replacements used with the stub.
     *
     * @return array
     */
    protected function replacements(): array
    {
        [$labels, $values] = $this->labelsAndValues();

        return [
            'modelPluralCapitalized' => $this->modelPluralCapitalized(),
            'modelPlural' => $this->modelPlural(),
            'modelSingular' => $this->modelSingular(),
            'labels' => $labels,
            'values' => $values,
        ];
    }

    protected function modelSingular()
    {
        return Word::variableSingular($this->table->name());
    }

    protected function modelPlural()
    {
        return Word::variable($this->table->name());
    }

    protected function modelPluralCapitalized()
    {
        return Word::labelUpperPlural($this->table->name());
    }

    protected function visibleColumns()
    {
        return collect($this->table->columns())->filter(function ($column) {
            return ! in_array($column, ['created_at', 'updated_at', 'deleted_at']);
        });
    }

    protected function labelsAndValues()
    {
        $labels = [];
        $values = [];

        foreach ($this->visibleColumns() as $column) {
            $builder = app(AccessorBuilder::class, ['table' => $this->table]);

            $labels[] = '<th>' . $builder->label($column) . '</th>';
            $values[] = '<td>' . $builder->viewAccessor($column, $this->modelSingular()) . '</td>';
        }

        return [$labels, $values];
    }
}
