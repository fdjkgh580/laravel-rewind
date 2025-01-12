<?php

namespace AvocetShores\LaravelRewind\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \AvocetShores\LaravelRewind\LaravelRewind
 */
class LaravelRewind extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AvocetShores\LaravelRewind\LaravelRewind::class;
    }
}
