<?php

namespace AvocetShores\LaravelRewind\Traits;

use AvocetShores\LaravelRewind\Exceptions\InvalidConfigurationException;
use AvocetShores\LaravelRewind\LaravelRewindServiceProvider;
use AvocetShores\LaravelRewind\Models\RewindRevision;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * Trait Rewindable
 *
 * When added to an Eloquent model, this trait will:
 *  - Listen to model events (creating/updating/deleting).
 *  - Capture "old" and "new" values for trackable attributes.
 *  - Store those values in a "rewind_revisions" table with a version number.
 *  - Provide a relationship to access the revision records as an audit log.
 */
trait Rewindable
{
    public bool $disableRewindEvents = false;

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
            $model->recordRevision();
        });

        static::deleted(function ($model) {
            if (! empty($model->disableRewindEvents)) {
                return;
            }
            $model->recordRevision();
        });
    }

    /**
     * A hasMany relationship to the revision records.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(RewindRevision::class, 'model_id')
            ->where('model_type', static::class);
    }

    /**
     * Capture the difference between old and new values, and store them in the database.
     *
     * @return void
     * @throws InvalidConfigurationException
     */
    protected function recordRevision(): void
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
                || array_key_exists($attribute, $dirty)
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
        $nextVersion = ($this->revisions()->max('version') ?? 0) + 1;

        // Create the revision record using the configured model.
        $modelClass = LaravelRewindServiceProvider::determineRewindRevisionModel();

        // Create a new revision record
        $modelClass::create([
            'model_type' => static::class,
            'model_id' => $this->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'version' => $nextVersion,
            config('laravel-rewind.user_id_column') => $this->getTrackUser(),
        ]);
    }

    /**
     * Determine which attributes should be tracked.
     */
    protected function getRewindableAttributes(): array
    {
        // If the model has $rewindAll set to true, track all
        if (property_exists($this, 'rewindAll') && $this->rewindAll) {
            return array_keys($this->getAttributes());
        }

        // If the package config is set to track all by default
        // and the model doesn't override that, track all
        if (
            config('laravel-rewind.tracks_all_by_default') &&
            ! property_exists($this, 'rewindable')
        ) {
            return array_keys($this->getAttributes());
        }

        // Otherwise, if a $rewindable array is defined, use it
        if (property_exists($this, 'rewindable') && is_array($this->rewindable)) {
            return $this->rewindable;
        }

        // If none of the above, default to an empty array
        // meaning we don't track anything on this model
        return [];
    }

    /**
     * Get the user ID if tracking is enabled, otherwise null.
     *
     * @return int|string|null
     */
    protected function getTrackUser()
    {
        if (! config('laravel-rewind.track_user')) {
            return null;
        }

        return optional(Auth::user())->id;
    }

    /**
     * Determine if the model was just deleted.
     * Useful to store a revision for the delete action if needed.
     */
    protected function wasDeleted(): bool
    {
        // "isDirty('deleted_at')" could help if soft deleting,
        // but for a permanent delete you need to check events differently.
        // For now, we simply check the "exists" property:
        return ! $this->exists;
    }
}
