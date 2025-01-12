<?php

namespace AvocetShores\LaravelRewind\Services;

use AvocetShores\LaravelRewind\Exceptions\LaravelRewindException;
use AvocetShores\LaravelRewind\LaravelRewindServiceProvider;
use AvocetShores\LaravelRewind\Traits\Rewindable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RewindManager
{
    /**
     * Undo the most recent change by jumping to the version before the current one.
     *
     * @return bool True if successfully reverted; False otherwise.
     *
     * @throws LaravelRewindException
     */
    public function undo(Model $model): bool
    {
        // First, make sure the model implements the Rewindable trait.
        $this->assertRewindable($model);

        // Find the current (highest) version for this model.
        $currentRevision = $model->revisions()->orderBy('version', 'desc')->first();

        // If there's no revision or if we're at version 1, there's nothing to undo.
        if (! $currentRevision || $currentRevision->version <= 1) {
            return false;
        }

        // Next, find the revision representing the state just before the current version.
        $previousRevision = $model->revisions()
            ->where('version', '<', $currentRevision->version)
            ->orderBy('version', 'desc')
            ->first();

        // If somehow there's no previous revision, abort.
        if (! $previousRevision) {
            return false;
        }

        // Revert to previous revision
        return $this->applyRevision($model, $previousRevision->version);
    }

    /**
     * Redo the next revision if we have undone one.
     *
     * @throws LaravelRewindException
     */
    public function redo(Model $model): bool
    {
        // First, make sure the model implements the Rewindable trait.
        $this->assertRewindable($model);

        // Identify the current version by comparing the model’s actual data
        // with the highest revision’s data. In a simple approach, assume
        // the "current" version is the highest.
        $currentVersion = $this->determineCurrentVersion($model);

        // Find the next revision after the current version.
        $nextRevision = $model->revisions()
            ->where('version', '>', $currentVersion)
            ->orderBy('version', 'asc')
            ->first();

        // If none, there’s no “redo” to perform.
        if (! $nextRevision) {
            return false;
        }

        return $this->applyRevision($model, $nextRevision->version);
    }

    /**
     * Jump directly to a specified version.
     *
     * @throws LaravelRewindException
     */
    public function revertToVersion(Model $model, int $version): bool
    {
        // First, make sure the model implements the Rewindable trait.
        $this->assertRewindable($model);

        // Validate that the version exists:
        $revision = $model->revisions()->where('version', $version)->first();

        if (! $revision) {
            return false;
        }

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
        // First, make sure the model implements the Rewindable trait.
        $this->assertRewindable($model);

        $revisionToApply = $model->revisions()->where('version', $targetVersion)->first();
        if (! $revisionToApply) {
            throw new LaravelRewindException('Revision not found.');
        }

        // Prepare the new_values to be applied to the model
        $attributes = $revisionToApply->new_values ?: [];

        // Determine if we want to log a new revision for this revert/redo action
        $shouldRecordRewind = config('laravel-rewind.record_rewinds', false) ||
            (
                method_exists($model, 'shouldRecordRewinds') &&
                $model->shouldRecordRewinds()
            );

        // Before we apply the revert, capture the model's current state
        // so we can store it as old_values if we decide to create a revision.
        $previousModelState = $model->attributesToArray();

        DB::transaction(function () use ($model, $attributes, $shouldRecordRewind, $previousModelState) {

            // Temporarily tell the Rewindable trait to skip storing a new revision on this revert/redo update.
            $model->disableRewindEvents = true;

            // Update the model’s attributes to the "target" revision state
            foreach ($attributes as $key => $value) {
                $model->setAttribute($key, $value);
            }
            $model->save();

            // Re-enable normal event handling
            $model->disableRewindEvents = false;

            // If desired, create a brand-new revision to record that
            // we changed the model's state.
            //    - old_values = the state of the model before rewind
            //    - new_values = the state we just applied
            if ($shouldRecordRewind) {
                // Retrieve the configured revision model
                $rewindModelClass = LaravelRewindServiceProvider::determineRewindRevisionModel();

                // The next version in sequence
                $nextVersion = ($model->revisions()->max('version') ?? 0) + 1;

                $rewindModelClass::create([
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'old_values' => $previousModelState,
                    'new_values' => $attributes,
                    'version' => $nextVersion,

                    config('laravel-rewind.user_id_column') => $model->getTrackUser(),
                ]);
            }
        });

        return true;
    }

    /**
     * Determine the model’s current version number in a simple manner.
     * We assume the highest revision in the table is the correct "current" state.
     * TODO : Update this
     */
    protected function determineCurrentVersion(Model $model): int
    {
        $latest = $model->revisions()->orderBy('version', 'desc')->first();

        return $latest ? $latest->version : 0;
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
}
