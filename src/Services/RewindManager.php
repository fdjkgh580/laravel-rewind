<?php

namespace AvocetShores\LaravelRewind\Services;

use AvocetShores\LaravelRewind\Exceptions\LaravelRewindException;
use AvocetShores\LaravelRewind\Exceptions\VersionDoesNotExistException;
use AvocetShores\LaravelRewind\LaravelRewindServiceProvider;
use AvocetShores\LaravelRewind\Traits\Rewindable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RewindManager
{
    /**
     * Undo the most recent change by jumping from current_version
     * to the previous version, if any.
     *
     * @throws LaravelRewindException
     */
    public function undo($model): bool
    {
        $this->assertRewindable($model);

        // Identify the model's actual current version
        $currentVersion = $this->determineCurrentVersion($model);

        if ($currentVersion <= 1) {
            // If the model is at version 0 or 1, there’s nothing to undo back to
            return false;
        }

        // Find the previous version
        $previousVersion = $model->versions()
            ->where('version', '<', $currentVersion)
            ->orderBy('version', 'desc')
            ->first();

        if (! $previousVersion) {
            return false;
        }

        // Apply the version for the previous version
        return $this->applyVersion($model, $previousVersion->version);
    }

    /**
     * Fast-forward (redo) to the next version, if one exists.
     *
     * @throws LaravelRewindException
     */
    public function redo($model): bool
    {
        $this->assertRewindable($model);

        // Identify the model's actual current version
        $currentVersion = $this->determineCurrentVersion($model);

        // Find the next higher version
        $nextVersion = $model->versions()
            ->where('version', '>', $currentVersion)
            ->orderBy('version', 'asc')
            ->first();

        if (! $nextVersion) {
            return false;
        }

        // Apply the next version
        return $this->applyVersion($model, $nextVersion->version);
    }

    /**
     * Jump directly to a specified version.
     *
     * @throws LaravelRewindException
     */
    public function goToVersion($model, int $version): bool
    {
        $this->assertRewindable($model);

        // Validate the target version
        $version = $model->versions()->where('version', $version)->first();
        if (! $version) {
            throw new VersionDoesNotExistException('The specified version does not exist.');
        }

        // Apply the version
        return $this->applyVersion($model, $version->version);
    }

    /**
     * Core function to apply the state of a version to the model.
     * Optionally create a new version if config or the model says so.
     *
     * @throws LaravelRewindException
     */
    protected function applyVersion($model, int $targetVersion): bool
    {
        // First, make sure the model implements the Rewindable trait
        $this->assertRewindable($model);

        $versionToApply = $model->versions()->where('version', $targetVersion)->first();
        if (! $versionToApply) {
            throw new VersionDoesNotExistException('The specified version does not exist.');
        }

        // Prepare the new_values to be applied to the model
        $attributes = $versionToApply->new_values ?: [];

        // Determine if we want to log a new version for this revert/redo action
        $shouldRecordRewind = config('rewind.record_rewinds', false)
            || (method_exists($model, 'shouldRecordRewinds') && $model->shouldRecordRewinds());

        // Capture the model's current state so we can store it as old_values if we create a version
        $previousModelState = $model->attributesToArray();

        DB::transaction(function () use ($model, $attributes, $shouldRecordRewind, $previousModelState, $versionToApply) {
            // Temporarily disable normal Rewindable event handling
            $model->disableRewindEvents();

            // Update the model’s attributes to the "target" version state
            foreach ($attributes as $key => $value) {
                $model->setAttribute($key, $value);
            }

            // Save the new current_version if the model has the column
            if ($this->modelHasCurrentVersionColumn($model)) {
                $model->current_version = $versionToApply->version;
            }

            $model->save();

            // Re-enable normal event handling
            $model->enableRewindEvents();

            // If desired, create a new version capturing the revert/redo event
            if ($shouldRecordRewind) {
                $rewindModelClass = LaravelRewindServiceProvider::determineRewindVersionModel();
                $nextVersion = ($model->versions()->max('version') ?? 0) + 1;

                $rewindModelClass::create([
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'old_values' => $previousModelState,
                    'new_values' => $attributes,
                    'version' => $nextVersion,
                    config('rewind.user_id_column') => $model->getRewindTrackUser(),
                ]);

                // We do not update the current_version column here, as that would disable the ability to "redo" back to the current state
            }
        });

        return true;
    }

    /**
     * Determine the model's current version.
     *
     * If a current_version column exists, return it.
     * Otherwise, fallback to the highest version from the versions table (a best guess).
     */
    protected function determineCurrentVersion($model): int
    {
        if ($this->modelHasCurrentVersionColumn($model)) {
            // Use the stored current_version, defaulting to 0
            return $model->current_version ?? 0;
        }

        // If there's no current_version column, fallback to the highest known version
        return $model->versions()->max('version') ?? 0;
    }

    /**
     * Ensure the model uses the Rewindable trait.
     *
     * @throws LaravelRewindException
     */
    protected function assertRewindable($model): void
    {
        if (collect(class_uses_recursive($model::class))->doesntContain(Rewindable::class)) {
            throw new LaravelRewindException('Model must use the Rewindable trait to be rewound.');
        }
    }

    /**
     * Check if the model's table has a 'current_version' column.
     */
    protected function modelHasCurrentVersionColumn($model): bool
    {
        return Schema::connection($model->getConnectionName())
            ->hasColumn($model->getTable(), 'current_version');
    }
}
