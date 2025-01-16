<?php

namespace AvocetShores\LaravelRewind\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @see \AvocetShores\LaravelRewind\Services\RewindManager
 *
 * @method static bool rewind(Model $model, int $steps = 1) Rewind by a specified number of steps
 * @method static bool fastForward(Model $model, int $steps = 1) Fast-forward by a specified number of steps
 * @method static bool goTo(Model $model, int $version) Jump to a specific version
 */
class Rewind extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-rewind-manager';
    }
}
