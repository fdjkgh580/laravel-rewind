<?php

namespace AvocetShores\LaravelRewind\Tests\Models;

use AvocetShores\LaravelRewind\Traits\Rewindable;
use Illuminate\Database\Eloquent\Model;

class PostWithExcludedAttributes extends Model
{
    use Rewindable;

    protected $table = 'posts';

    protected $fillable = [
        'user_id',
        'title',
        'body',
    ];

    public static function excludedFromVersioning(): array
    {
        return ['body'];
    }
}
