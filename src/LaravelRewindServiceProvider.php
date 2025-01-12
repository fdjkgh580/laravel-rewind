<?php

namespace AvocetShores\LaravelRewind;

use AvocetShores\LaravelRewind\Commands\LaravelRewindCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            ->hasMigration('create_rewind_revisions_table')
            ->hasCommand(LaravelRewindCommand::class);
    }
}
