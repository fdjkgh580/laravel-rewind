<?php

namespace AvocetShores\LaravelRewind;

use AvocetShores\LaravelRewind\Commands\AddVersionTrackingColumnCommand;
use AvocetShores\LaravelRewind\Commands\LaravelRewindCommand;
use AvocetShores\LaravelRewind\Exceptions\InvalidConfigurationException;
use AvocetShores\LaravelRewind\Models\RewindRevision;
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
            ->hasMigration('create_rewind_revisions_table')
            ->hasCommand(AddVersionTrackingColumnCommand::class);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function determineRewindRevisionModel(): string
    {
        $rewindModel = config('laravel-rewind.rewind_revision_model') ?? RewindRevision::class;

        if (! is_a($rewindModel, Model::class, true)) {
            throw InvalidConfigurationException::modelIsNotValid($rewindModel);
        }

        return $rewindModel;
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function getRewindRevisionModelInstance(): Model
    {
        $rewindModel = static::determineRewindRevisionModel();

        return new $rewindModel();
    }
}
