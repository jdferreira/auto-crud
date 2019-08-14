<?php

namespace Ferreira\AutoCrud;

use Ferreira\AutoCrud\Commands\AutoCrudCommand;
use Ferreira\AutoCrud\Database\DatabaseInformation;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register this service provider by adding the autocrud artisan command.
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(AutoCrudCommand::class);
        }

        $this->app->singleton(DatabaseInformation::class);
    }
}
