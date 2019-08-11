<?php

namespace Ferreira\AutoCrud;

use Ferreira\AutoCrud\Commands\AutoCrudCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register this service provider by adding the autocrud artisan
     * command, as well as by merging the configuration file with
     * the general configuration file of the full application.
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(AutoCrudCommand::class);
        }
    }
}
