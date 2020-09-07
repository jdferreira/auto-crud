<?php

namespace Ferreira\AutoCrud\Commands;

use Illuminate\Console\Command;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseInformation;
use Ferreira\AutoCrud\Generators\ViewEditGenerator;
use Ferreira\AutoCrud\Generators\ViewShowGenerator;
use Ferreira\AutoCrud\Generators\ViewIndexGenerator;
use Ferreira\AutoCrud\Generators\LayoutViewGenerator;
use Ferreira\AutoCrud\Generators\ViewCreateGenerator;

class ViewCommand extends Command
{
    use Concerns\TableBasedCommand;

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
        $this->laravel->make(LayoutViewGenerator::class)->save();

        $generators = [
            ViewIndexGenerator::class,
            ViewCreateGenerator::class,
            ViewEditGenerator::class,
            ViewShowGenerator::class,
        ];

        foreach ($generators as $generator) {
            $this->handleMultipleTables($generator);
        }
    }
}
