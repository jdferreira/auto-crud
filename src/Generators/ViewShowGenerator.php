<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
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
            'views/' . Str::snake(Str::plural($this->table->name())) . '/show.blade.php'
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
        return Str::ucfirst(Str::camel(Str::singular($this->table->name())));
    }

    private function values()
    {
        $result = [];

        foreach ($this->table->columns() as $column) {
            $builder = new AccessorBuilder($this->table, $column);

            $result[] = "    <tr><th>$builder->label</th><td>$builder->accessor</td></tr>";
        }

        return $result;
    }

    private function buttons()
    {
        $singular = Str::camel(Str::singular($this->table->name()));
        $plural = Str::plural($singular);

        return [
            "<a href=\"{{ route('$plural.edit', ['$singular' => \$$singular]) }}\">Edit</a>",
            "<form action=\"{{ route('$plural.destroy', ['$singular' => \$$singular]) }}\" method=\"POST\">",
            '    @method(\'DELETE\')',
            '    @csrf',
            '    <button type="submit">Delete</button>',
            '</form>',
        ];
    }
}
