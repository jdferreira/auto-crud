<?php

namespace Ferreira\AutoCrud\Commands;

use Illuminate\Console\Command;
use Ferreira\AutoCrud\Generators\FactoryGenerator;

class FactoryCommand extends Command
{
    use Concerns\TableBasedCommand;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        autocrud:factory
        {--table=* : The table names to base the generation on. Defaults to all tables in the database that are not pivot tables.}
        {--dir= : The directory where the models will be written to.}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a factory file for the tables defined in this project\'s migrations';

    /**
     * Generate the requested files.
     */
    public function handle()
    {
        $this->handleMultipleTables(FactoryGenerator::class);
    }
}
