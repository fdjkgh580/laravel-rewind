<?php

namespace AvocetShores\LaravelRewind\Commands;

use Illuminate\Console\Command;

class LaravelRewindCommand extends Command
{
    public $signature = 'laravel-rewind';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
