<?php

namespace AvocetShores\LaravelRewind\Tests\Models;

use AvocetShores\LaravelRewind\Traits\Rewindable;
use Illuminate\Database\Eloquent\Model;

class PostWithoutRewindableAttributes extends Model
{
    use Rewindable;

    // rewindAll and rewindable are not set

    protected $table = 'posts';

    protected $fillable = [
        'user_id',
        'title',
        'body',
    ];
}
