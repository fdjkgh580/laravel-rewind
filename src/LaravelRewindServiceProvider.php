<?php

namespace AvocetShores\LaravelRewind;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use AvocetShores\LaravelRewind\Commands\LaravelRewindCommand;

class LaravelRewindServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-rewind')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_rewind_table')
            ->hasCommand(LaravelRewindCommand::class);
    }
}
