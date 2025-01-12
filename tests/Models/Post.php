<?php

namespace AvocetShores\LaravelRewind\Tests\Models;

use AvocetShores\LaravelRewind\Traits\Rewindable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Rewindable;

    protected $table = 'posts';

    protected bool $rewindAll = true;

    protected $fillable = [
        'user_id',
        'title',
        'body',
    ];


}
