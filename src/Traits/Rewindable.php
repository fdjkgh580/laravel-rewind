<?php

namespace AvocetShores\LaravelRewind\Traits;

use AvocetShores\LaravelRewind\Models\RewindVersion;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Trait Rewindable
 *
 * When added to an Eloquent model, this trait will:
 *  - Listen to model events (creating/updating/deleting).
 *  - Capture "old" and "new" values for trackable attributes.
 *  - Store those values in a "rewind_versions" table with a version number.
 *  - Provide a relationship to access the version records as an audit log.
 */
trait Rewindable
{
    protected bool $disableRewindEvents = false;

    protected function getExcludedRewindableAttributes(): array
    {
        // Merge the default exclusions with any custom exclusions
        $defaultExclusions = [
            $this->getKeyName(),
            'created_at',
            'updated_at',
            'current_version',
        ];

        return array_unique(array_merge($defaultExclusions, $this->excludeFromRewindable()));
    }

    public static function excludeFromRewindable(): array
    {
        return [];
    }

    /**
     * Boot the trait. Registers relevant event listeners.
     */
    public static function bootRewindable(): void
    {
        static::saved(function ($model) {
            // If the model signals it does not want Rewindable events, skip
            if (! empty($model->disableRewindEvents)) {
                return;
            }
            $model->recordVersion();
        });

        static::deleted(function ($model) {
            if (! empty($model->disableRewindEvents)) {
                return;
            }
            $model->recordVersion();
        });
    }

    /**
     * A hasMany relationship to the version records.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RewindVersion::class, 'model_id')
            ->where('model_type', static::class);
    }

    /**
     * Capture the difference between old and new values, and store them in the database.
     */
    protected function recordVersion(): void
    {
        // Get the attributes that have changed (or all if new model/deleted).
        // If nothing changed (e.g., a save with no modifications), do nothing.
        $dirty = $this->getDirty();
        if (empty($dirty) && ! $this->wasRecentlyCreated && ! $this->wasDeleted()) {
            return;
        }

        // Figure out which attributes to track
        $attributesToTrack = $this->getRewindableAttributes();

        // Build arrays of old/new values for only the attributes we want to track
        $oldValues = [];
        $newValues = [];

        // For each attribute to track, see if it changed (or if creating/deleting)
        foreach ($attributesToTrack as $attribute) {
            // If the model was just created, there's no "old" value,
            // but let's check the original if it exists.
            $originalValue = $this->getOriginal($attribute);

            // If the attribute is truly changed, or if wasRecentlyCreated/wasDeleted
            if (
                $this->wasRecentlyCreated
                || $this->wasDeleted()
                || Arr::exists($dirty, $attribute)
            ) {
                $oldValues[$attribute] = $originalValue;
                $newValues[$attribute] = $this->getAttribute($attribute);
            }
        }

        // If there's nothing to store, skip
        if (count($oldValues) === 0 && count($newValues) === 0) {
            return;
        }

        // Get the next version number for this model
        $nextVersion = ($this->versions()->max('version') ?? 0) + 1;

        // Determine if we should create a full snapshot
        $interval = config('rewind.snapshot_interval', 10);
        $isSnapshot = ($nextVersion % $interval === 0) || $nextVersion === 1;

        if ($isSnapshot) {
            $allAttributes = $this->getAttributes();
            // Filter down to our rewindable attributes
            $newValues = Arr::only($allAttributes, $attributesToTrack);
        }

        // Create a new version record
        RewindVersion::create([
            'model_type' => static::class,
            'model_id' => $this->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'version' => $nextVersion,
            config('rewind.user_id_column') => $this->getRewindTrackUser(),
            'is_snapshot' => $isSnapshot,
        ]);

        // Update the current_version column if it exists
        if ($this->modelHasCurrentVersionColumn()) {
            $this->disableRewindEvents();

            $this->current_version = $nextVersion;
            $this->save();

            $this->enableRewindEvents();
        }
    }

    protected function modelHasCurrentVersionColumn(): bool
    {
        return $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'current_version');
    }

    /**
     * Determine which attributes should be tracked.
     */
    protected function getRewindableAttributes(): array
    {
        // Track everything except timestamps, primary key, and current_version
        return array_keys(Arr::except(
            $this->getAttributes(),
            $this->getExcludedRewindableAttributes()
        ));
    }

    /**
     * Get the user ID if tracking is enabled, otherwise null.
     *
     * @return int|string|null
     */
    public function getRewindTrackUser()
    {
        if (! config('rewind.track_user')) {
            return null;
        }

        return optional(Auth::user())->getKey();
    }

    /**
     * Determine if the model was just deleted.
     * Useful to store a version for the delete action if needed.
     */
    protected function wasDeleted(): bool
    {
        // "isDirty('deleted_at')" could help if soft deleting,
        // but for a permanent delete you need to check events differently.
        // For now, we simply check the "exists" property:
        return ! $this->exists;
    }

    public function disableRewindEvents(): void
    {
        $this->disableRewindEvents = true;
    }

    public function enableRewindEvents(): void
    {
        $this->disableRewindEvents = false;
    }
}
