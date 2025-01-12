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
    public function undo(Model $model): bool
    {
        $this->assertRewindable($model);

        // Identify the model's actual current version
        $currentVersion = $this->determineCurrentVersion($model);

        if ($currentVersion <= 1) {
            // If the model is at version 0 or 1, there’s nothing to undo back to
            return false;
        }

        // Find the previous version
        $previousRevision = $model->revisions()
            ->where('version', '<', $currentVersion)
            ->orderBy('version', 'desc')
            ->first();

        if (! $previousRevision) {
            return false;
        }

        // Apply the revision for the previous version
        return $this->applyRevision($model, $previousRevision->version);
    }

    /**
     * Fast-forward (redo) to the next version, if one exists.
     *
     * @throws LaravelRewindException
     */
    public function redo(Model $model): bool
    {
        $this->assertRewindable($model);

        // Identify the model's actual current version
        $currentVersion = $this->determineCurrentVersion($model);

        // Find the next higher version
        $nextRevision = $model->revisions()
            ->where('version', '>', $currentVersion)
            ->orderBy('version', 'asc')
            ->first();

        if (! $nextRevision) {
            return false;
        }

        // Apply the next revision
        return $this->applyRevision($model, $nextRevision->version);
    }

    /**
     * Jump directly to a specified version.
     *
     * @throws LaravelRewindException
     */
    public function goToVersion(Model $model, int $version): bool
    {
        $this->assertRewindable($model);

        // Validate the target version
        $revision = $model->revisions()->where('version', $version)->first();
        if (! $revision) {
            throw new VersionDoesNotExistException('The specified version does not exist.');
        }

        // Apply the revision
        return $this->applyRevision($model, $version);
    }

    /**
     * Core function to apply the state of a revision to the model.
     * Optionally create a new revision if config or the model says so.
     *
     * @throws LaravelRewindException
     */
    protected function applyRevision(Model $model, int $targetVersion): bool
    {
        // First, make sure the model implements the Rewindable trait
        $this->assertRewindable($model);

        $revisionToApply = $model->revisions()->where('version', $targetVersion)->first();
        if (! $revisionToApply) {
            throw new VersionDoesNotExistException('The specified version does not exist.');
        }

        // Prepare the new_values to be applied to the model
        $attributes = $revisionToApply->new_values ?: [];

        // Determine if we want to log a new revision for this revert/redo action
        $shouldRecordRewind = config('laravel-rewind.record_rewinds', false)
            || (method_exists($model, 'shouldRecordRewinds') && $model->shouldRecordRewinds());

        // Capture the model's current state so we can store it as old_values if we create a revision
        $previousModelState = $model->attributesToArray();

        DB::transaction(function () use ($model, $attributes, $shouldRecordRewind, $previousModelState, $revisionToApply) {
            // Temporarily disable normal Rewindable event handling
            $model->disableRewindEvents = true;

            // Update the model’s attributes to the "target" revision state
            foreach ($attributes as $key => $value) {
                $model->setAttribute($key, $value);
            }

            // Save the new current_version if the model has the column
            if ($this->modelHasCurrentVersionColumn($model)) {
                $model->current_version = $revisionToApply->version;
            }

            $model->save();

            // Re-enable normal event handling
            $model->disableRewindEvents = false;

            // If desired, create a new revision capturing the revert/redo event
            if ($shouldRecordRewind) {
                $rewindModelClass = LaravelRewindServiceProvider::determineRewindRevisionModel();
                $nextVersion = ($model->revisions()->max('version') ?? 0) + 1;

                $rewindModelClass::create([
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'old_values' => $previousModelState,
                    'new_values' => $attributes,
                    'version' => $nextVersion,
                    config('laravel-rewind.user_id_column') => $model->getTrackUser(),
                ]);

                // Update the current_version column if it exists
                if ($this->modelHasCurrentVersionColumn($model)) {
                    $model->disableRewindEvents = true;

                    $model->current_version = $nextVersion;
                    $model->save();

                    $model->disableRewindEvents = false;
                }
            }
        });

        return true;
    }

    /**
     * Determine the model's current version.
     *
     * If a current_version column exists, return it.
     * Otherwise, fallback to the highest version from the revisions table (a best guess).
     */
    protected function determineCurrentVersion(Model $model): int
    {
        if ($this->modelHasCurrentVersionColumn($model)) {
            // Use the stored current_version, defaulting to 0
            return $model->current_version ?? 0;
        }

        // If there's no current_version column, fallback to the highest known revision
        return $model->revisions()->max('version') ?? 0;
    }

    /**
     * Ensure the model uses the Rewindable trait.
     *
     * @throws LaravelRewindException
     */
    protected function assertRewindable(Model $model): void
    {
        if (collect(class_uses_recursive($model::class))->doesntContain(Rewindable::class)) {
            throw new LaravelRewindException('Model must use the Rewindable trait to be rewound.');
        }
    }

    /**
     * Check if the model's table has a 'current_version' column.
     */
    protected function modelHasCurrentVersionColumn(Model $model): bool
    {
        return Schema::connection($model->getConnectionName())
            ->hasColumn($model->getTable(), 'current_version');
    }
}
