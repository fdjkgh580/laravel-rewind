<?php

namespace AvocetShores\LaravelRewind\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewindRevision extends Model
{
    /**
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        if (! isset($this->connection)) {
            $this->setConnection(config('laravel-rewind.database_connection'));
        }

        if (! isset($this->table)) {
            $this->setTable(config('laravel-rewind.table_name'));
        }

        $this->fillable[] = config('laravel-rewind.user_id_column');
        $this->casts[config('laravel-rewind.user_id_column')] = 'integer';

        parent::__construct($attributes);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'version'    => 'integer',
    ];

    /**
     * Optional relationship to the user who made the change (if user tracking is enabled).
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        // Update this to reference your actual User model namespace if needed.
        return $this->belongsTo(
            config('laravel-rewind.user_model'),
            config('laravel-rewind.user_id_column')
        );
    }
}
