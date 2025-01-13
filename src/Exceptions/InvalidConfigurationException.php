<?php

namespace AvocetShores\LaravelRewind\Exceptions;

final class InvalidConfigurationException extends LaravelRewindException
{
    public static function ModelIsNotValid($model): self
    {
        return new self("The model [{$model}] isn't a valid Eloquent model.");
    }
}
