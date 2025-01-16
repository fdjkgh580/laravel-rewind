<?php

namespace AvocetShores\LaravelRewind;

use AvocetShores\LaravelRewind\Commands\AddVersionTrackingColumnCommand;
use AvocetShores\LaravelRewind\Exceptions\InvalidConfigurationException;
use AvocetShores\LaravelRewind\Models\RewindVersion;
use AvocetShores\LaravelRewind\Services\RewindManager;
use Illuminate\Database\Eloquent\Model;
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
            ->hasMigration('create_rewind_versions_table')
            ->hasCommand(AddVersionTrackingColumnCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton('laravel-rewind-manager', function () {
            return $this->app->make(RewindManager::class);
        });
    }
}
