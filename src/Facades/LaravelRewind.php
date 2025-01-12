<?php

namespace AvocetShores\LaravelRewind\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @see \AvocetShores\LaravelRewind\Services\RewindManager
 *
 * @method static bool undo(Model $model)    Undo the most recent change
 * @method static bool redo(Model $model)    Redo the next version
 * @method static bool goToVersion(Model $model, int $version)    Jump to a specific version
 */
class LaravelRewind extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-rewind-manager';
    }
}
