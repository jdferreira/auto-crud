<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Support\Str;
use Ferreira\AutoCrud\Validation\RuleGenerator;

class RequestGenerator extends BaseGenerator
{
    /**
     * Whether we need to use the Model class in this file.
     *
     * @var bool
     */
    private $needsModel = false;

    /**
     * Get the stub filename.
     */
    protected function stub(): string
    {
        return 'request.php.stub';
    }

    /**
     * Get the output filename. The returned value is relative to the
     * application's base directory (usually `app/`).
     */
    protected function filename(): string
    {
        return app_path('Http/Requests/' . $this->modelClass() . 'Request.php');
    }

    /**
     * Get the replacements to use with the stub, based on these generator options.
     */
    protected function replacements(): array
    {
        return [
            'modelClass' => $this->modelClass(),
            'rules' => $this->rules(),
            'useModel' => $this->useModel(),
        ];

        // Notice that the 'useModel' placeholder must be computed after the
        // 'rules', because to determine whether we need it, we must first
        // actually generate the rules.
    }

    private function modelClass()
    {
        return Str::studly(Str::singular($this->table->name()));
    }

    private function rules()
    {
        $this->needsModel = false;

        $lines = ['return ['];

        foreach ($this->table->columns() as $name) {
            $rule = app(RuleGenerator::class, [
                'table' => $this->table,
                'column' => $name,
            ]);

            $validation = $rule->generate();

            if ($validation === null) {
                continue;
            }

            $lines = array_merge($lines, $this->extendRule($validation, $name));

            if ($rule->needsModel()) {
                $this->needsModel = true;
            }
        }

        $lines[] = '];';

        if ($this->needsModel) {
            $lines = array_merge([
                "\$model = \$this->route('id') ? {$this->modelClass()}::find(\$this->input('id')) : null;",
                '',
            ], $lines);

            $this->needsModel = true;
        }

        return $lines;
    }

    private function extendRule(array $lines, $name)
    {
        $first = array_shift($lines);

        $lines = array_map(function ($line) {
            return "    $line";
        }, $lines);

        array_unshift($lines, "    '$name' => " . $first);

        $lines[count($lines) - 1] .= ',';

        return $lines;
    }

    private function useModel()
    {
        if (! $this->needsModel) {
            return '';
        }

        $namespace = $this->dir === ''
            ? 'App'
            : 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $this->dir);

        $class = $this->modelClass();

        return "use $namespace\\$class;";
    }
}
