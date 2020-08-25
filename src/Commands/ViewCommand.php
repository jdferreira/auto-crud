<?php

namespace Ferreira\AutoCrud\Commands;

use Illuminate\Console\Command;
use Ferreira\AutoCrud\Database\DatabaseInformation;
use Ferreira\AutoCrud\Generators\ViewEditGenerator;
use Ferreira\AutoCrud\Generators\ViewIndexGenerator;
use Ferreira\AutoCrud\Generators\ViewCreateGenerator;

class ViewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        autocrud:view
        {--table=* : The table names to base the generation on. Defaults to all tables in the database that are not pivot tables.}
        {--dir= : The directory where the models will be written to.}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the view files for the tables defined in this project\'s migrations';

    /**
     * Generate the requested files.
     */
    public function handle()
    {
        $requested = $this->option('table');

        $tablenames = count($requested) > 0
            ? $requested
            : $this->laravel->make(DatabaseInformation::class)->tablenames(false);

        $generators = [
            ViewIndexGenerator::class,
            ViewCreateGenerator::class,
            ViewEditGenerator::class,
        ];

        foreach ($generators as $generator) {
            foreach ($tablenames as $table) {
                $this->laravel->make($generator, [
                    'table' => $table,
                ])->save();
            }
        }
    }
}
