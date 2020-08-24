<?php

namespace Ferreira\AutoCrud\Commands;

use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class AutoCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        autocrud:make
        {--table=* : The table names to base the generation on. Defaults to all tables in the database that are not pivot tables.}
        {--dir= : The directory where the models will be written to.}
        {--skip-api : Whether to skip generating API routes.}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate crud-related files based on this project\'s migrations';

    /**
     * Generate the requested files.
     */
    public function handle()
    {
        if (! $this->valid()) {
            return 1;
        }

        $this->inner('autocrud:model');
        $this->inner('autocrud:controller');
        $this->inner('autocrud:factory');
        $this->inner('autocrud:seeder');
        $this->inner('autocrud:request');
        $this->inner('autocrud:route');
        $this->inner('autocrud:view');
    }

    private function valid()
    {
        $valid = true;

        $db = $this->laravel->make(DatabaseInformation::class);

        foreach ($this->option('table') as $tablename) {
            if ($db->table($tablename) === null) {
                $this->error("Table $tablename does not exist.");
                $valid = false;
            }
        }

        return $valid;
    }

    private function inner(string $command)
    {
        $definition = $this->getApplication()->find($command)->getDefinition();

        $definedArguments = array_keys(Arr::except($definition->getArguments(), 'command'));
        $definedOptions = array_keys($definition->getOptions());

        $arguments = collect($definedArguments)->mapWithKeys(function ($argument) {
            return [$argument => $this->argument($argument)];
        })->all();

        $options = collect($definedOptions)->mapWithKeys(function ($option) {
            return ["--$option" => $this->option($option)];
        })->all();

        // Go through all the arguments of the command,
        $this->call($command, $arguments + $options);
    }
}
