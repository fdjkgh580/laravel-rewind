<?php

use AvocetShores\LaravelRewind\Exceptions\InvalidConfigurationException;
use AvocetShores\LaravelRewind\LaravelRewindServiceProvider;

it('throws an exception if the configured class is not a model', function () {
    // Arrange
    config()->set('rewind.rewind_version_model', stdClass::class);

    // Act
    LaravelRewindServiceProvider::determineRewindVersionModel();
})->throws(InvalidConfigurationException::class);
