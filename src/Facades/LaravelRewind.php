<?php

namespace Avocet Shores\LaravelRewind\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Avocet Shores\LaravelRewind\LaravelRewind
 */
class LaravelRewind extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Avocet Shores\LaravelRewind\LaravelRewind::class;
    }
}
