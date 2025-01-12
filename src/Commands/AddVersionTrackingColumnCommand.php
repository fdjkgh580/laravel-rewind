<?php

namespace AvocetShores\LaravelRewind\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class AddVersionTrackingColumnCommand extends Command
{
    protected $signature = 'rewind:add-version {table? : The name of the table to add current_version column to}';

    protected $description = 'Generate a migration to add the current_version column to a given table.';

    protected string $stubPath = __DIR__.'/../../stubs/add_current_version_column.stub';

    public function handle()
    {
        $table = $this->argument('table');

        // If no table name provided, prompt user
        if (! $table) {
            $table = $this->ask('Which table do you want to add the current_version column to?');
        }

        if (! $table) {
            $this->error('A table name is required.');

            return 1;
        }

        // Determine the class name for the migration
        $className = 'AddCurrentVersionTo'.Str::studly($table).'Table';

        // Build the filename
        // e.g. 2023_01_01_000000_add_current_version_to_posts_table.php
        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp.'_add_current_version_to_'.$table.'_table.php';
        $fullPath = database_path('migrations/'.$fileName);

        // Read the stub and replace placeholders
        $stubContent = (new Filesystem)->get($this->stubPath);

        $stubContent = str_replace(
            ['DummyClass', 'DummyTable'],
            [$className, $table],
            $stubContent
        );

        // Write the final migration file
        (new Filesystem)->put($fullPath, $stubContent);

        $this->info("Migration created: {$fileName}");
        $this->info("Don't forget to run 'php artisan migrate'!");

        return 0;
    }
}
