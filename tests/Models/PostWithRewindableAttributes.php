<?php

namespace AvocetShores\LaravelRewind\Tests\Models;

use AvocetShores\LaravelRewind\Traits\Rewindable;
use Illuminate\Database\Eloquent\Model;

class PostWithRewindableAttributes extends Model
{
    use Rewindable;

    protected $table = 'posts';

    protected array $rewindable = [
        'title',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'body',
    ];
}
