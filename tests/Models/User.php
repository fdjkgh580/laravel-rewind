<?php

namespace AvocetShores\LaravelRewind\Tests\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model implements Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        $name = $this->getAuthIdentifierName();

        return $this->attributes[$name];
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword()
    {
        return $this->attributes['password'];
    }

    public function getRememberToken(): string
    {
        return 'token';
    }

    public function setRememberToken($value) {}

    public function getRememberTokenName(): string
    {
        return 'tokenName';
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
