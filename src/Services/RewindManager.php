<?php

namespace AvocetShores\LaravelRewind\Services;

use AvocetShores\LaravelRewind\Enums\ApproachMethod;
use AvocetShores\LaravelRewind\Exceptions\LaravelRewindException;
use AvocetShores\LaravelRewind\Exceptions\VersionDoesNotExistException;
use AvocetShores\LaravelRewind\Models\RewindVersion;
use AvocetShores\LaravelRewind\Traits\Rewindable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RewindManager
{
    public function __construct(
        protected ApproachEngine $approachEngine,
    ) {}

    /**
     * Undo the most recent change by jumping from current_version
     * to the previous version, if any.
     *
     * @throws LaravelRewindException
     */
    public function undo($model): void
    {
        $this->assertRewindable($model);
        $this->eagerLoadVersions($model);

        // Identify the model's actual current version
        $currentVersion = $this->determineCurrentVersion($model);

        if ($currentVersion <= 1) {
            // If the model is at version 0 or 1, there’s nothing to undo back to
            return;
        }

        $targetVersion = $currentVersion - 1;
        $targetVersionModel = $model->versions->where('version', $targetVersion)->first();

        if (! $targetVersionModel) {
            // If the target version doesn't exist, there's nothing to undo back to
            return;
        }

        // If the target version is a snapshot, apply it directly
        if ($targetVersionModel->is_snapshot) {
            $this->applySnapshot(
                model: $model,
                snapshotRecord: $targetVersionModel,
                shouldSave: true
            );

            $this->updateModelVersion($model, $targetVersion);

            return;
        }

        // Otherwise, apply partial diffs in reverse from currentVersion down to targetVersion
        $this->applyDiffs($model, $currentVersion, $targetVersion);
    }

    /**
     * Fast-forward (redo) to the next version, if one exists.
     *
     * @throws LaravelRewindException
     */
    public function redo($model): void
    {
        $this->assertRewindable($model);
        $this->eagerLoadVersions($model);

        // Identify the model's current version
        $currentVersion = $this->determineCurrentVersion($model);

        $targetVersion = $currentVersion + 1;
        $nextVersion = $model->versions->where('version', $targetVersion)->first();

        if (! $nextVersion) {
            return;
        }

        // If the next version is a snapshot, apply it directly
        if ($nextVersion->is_snapshot) {
            $this->applySnapshot(
                model: $model,
                snapshotRecord: $nextVersion,
                shouldSave: true
            );

            $this->updateModelVersion($model, $targetVersion);
            return;
        }

        // Apply the next version
        $this->applyDiffs($model, $currentVersion, $nextVersion->version);
    }

    /**
     * Jump directly to a specified version.
     *
     * @throws LaravelRewindException
     */
    public function goTo($model, int $targetVersion): void
    {
        $this->assertRewindable($model);
        $this->eagerLoadVersions($model);

        // Validate the target version
        $targetModel = $model->versions->where('version', $targetVersion)->first();
        if (! $targetModel) {
            throw new VersionDoesNotExistException('The specified version does not exist.');
        }

        // Identify the model's current version
        $currentVersion = $this->determineCurrentVersion($model);

        // Run the approach engine to determine the best way to get to the target version
        $bestApproach = $this->approachEngine->run($model, $currentVersion, $targetVersion);

        // Apply the best approach
        switch ($bestApproach->method) {
            case ApproachMethod::None:
                // No action needed
                break;
            case ApproachMethod::Direct:
                $this->applyDiffs($model, $currentVersion, $targetVersion);
                break;
            case ApproachMethod::From_Snapshot:
                $this->applyDiffsFromSnapshot($model, $bestApproach->snapshot, $targetVersion);
                break;
        }
    }

    protected function applyDiffsFromSnapshot(Model $model, RewindVersion $snapshotRecord, int $targetVersion): void
    {
        // Apply snapshot without saving to reduce the number of save operations
        $this->applySnapshot($model, $snapshotRecord);

        $this->applyDiffs($model, $snapshotRecord->version, $targetVersion);
    }

    /**
     * Apply partial diffs in reverse or forward from currentVersion to targetVersion.
     *
     * Example: If you're at version 10 and want to go to version 7, you'll:
     *  - get diff for v10, revert it,
     *  - then v9, revert it,
     *  - then v8, revert it,
     *  - stopping once we reach v7.
     */
    protected function applyDiffs($model, int $currentVersion, int $targetVersion): void
    {
        $this->eagerLoadVersions($model);

        DB::transaction(function () use ($model, $currentVersion, $targetVersion) {

            if ($currentVersion > $targetVersion) {
                // Step downward from currentVersion-1 until targetVersion
                for ($ver = $currentVersion; $ver > $targetVersion; $ver--) {
                    $versionRec = $model->versions
                        ->where('version', $ver)
                        ->where('is_snapshot', false)
                        ->first();

                    // If there's no partial diff for $ver (e.g. if it was a snapshot or doesn't exist), skip
                    if (! $versionRec) {
                        continue;
                    }

                    // Reverse the partial diff by applying "old_values"
                    $this->applyPartialDiffReverse($model, $versionRec);
                }
            } else {
                // Step upward from currentVersion+1 until targetVersion
                for ($ver = $currentVersion + 1; $ver <= $targetVersion; $ver++) {
                    $versionRec = $model->versions
                        ->where('version', $ver)
                        ->where('is_snapshot', false)
                        ->first();

                    // If there's no partial diff for $ver (e.g. if it was a snapshot or doesn't exist), skip
                    if (! $versionRec) {
                        continue;
                    }

                    // Apply the partial diff
                    $this->applyPartialDiff($model, $versionRec);
                }
            }

            $this->updateModelVersion($model, $targetVersion);

        });
    }

    /**
     * Update the model's current_version to the specified version without triggering Rewind events
     *
     * @param $model
     * @param int $version
     * @return void
     */
    protected function updateModelVersion($model, int $version): void
    {
        if (! $this->modelHasCurrentVersionColumn($model)) {
            return;
        }

        $model->disableRewindEvents();

        $model->current_version = $version;
        $model->save();

        $model->enableRewindEvents();
    }

    protected function applyPartialDiff($model, $versionRec): void
    {
        $newValues = $versionRec->new_values;

        foreach ($newValues as $key => $value) {
            $model->setAttribute($key, $value);
        }
    }

    /**
     * Reverse partial diff means we set the model's attributes to "old_values"
     * which represent the state before that version was applied.
     */
    protected function applyPartialDiffReverse($model, $versionRec): void
    {
        $oldValues = $versionRec->old_values;

        foreach ($oldValues as $key => $value) {
            $model->setAttribute($key, $value);
        }
    }

    /**
     * Apply a snapshot (full new_values).
     * Optionally save the model after applying the snapshot.
     */
    protected function applySnapshot($model, RewindVersion $snapshotRecord, bool $shouldSave = false): void
    {
        $model->disableRewindEvents();
        foreach (($snapshotRecord->new_values ?? []) as $key => $value) {
            $model->setAttribute($key, $value);
        }

        if ($shouldSave) {
            $model->save();
        }

        $model->enableRewindEvents();
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

    protected function eagerLoadVersions(Model $model): void
    {
        if (! $model->relationLoaded('versions')) {
            $model->load('versions');
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
