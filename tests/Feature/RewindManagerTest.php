<?php

use AvocetShores\LaravelRewind\Exceptions\VersionDoesNotExistException;
use AvocetShores\LaravelRewind\Facades\Rewind;
use AvocetShores\LaravelRewind\Tests\Models\Post;
use AvocetShores\LaravelRewind\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a user
    $this->user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
    ]);

    // Set the user as the currently authenticated user
    test()->actingAs($this->user);
});

it('rewinds a model to the previous version on undo', function () {
    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'body' => 'Original Body',
    ]);

    $post->refresh();

    // Assert the model has current_version set to 1
    $this->assertSame(1, $post->current_version);

    $post->update([
        'title' => 'Updated Title',
        'body' => 'Updated Body',
    ]);

    $this->assertSame(2, $post->current_version);

    // Act: Undo the last version
    Rewind::undo($post);

    // Assert: The model should be reverted to the previous version
    $this->assertSame(1, $post->current_version);
    $this->assertSame('Original Title', $post->title);
    $this->assertSame('Original Body', $post->body);
});

it('rewinds a model to the next version on redo', function () {
    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'body' => 'Original Body',
    ]);

    $post->refresh();

    // Assert the model has current_version set to 1
    $this->assertSame(1, $post->current_version);

    $post->update([
        'title' => 'Updated Title',
        'body' => 'Updated Body',
    ]);

    $this->assertSame(2, $post->current_version);

    // Act: Undo the last version
    Rewind::undo($post);

    // Assert: The model should be reverted to the previous version
    $this->assertSame(1, $post->current_version);
    $this->assertSame('Original Title', $post->title);
    $this->assertSame('Original Body', $post->body);

    // Act: Redo the last version
    Rewind::redo($post);

    // Assert: The model should be reverted to the next version
    $this->assertSame(2, $post->current_version);
    $this->assertSame('Updated Title', $post->title);
    $this->assertSame('Updated Body', $post->body);
});

it('creates a new max version when a model is updated while on a previous version', function () {
    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'body' => 'Original Body',
    ]);

    $post->refresh();

    // Assert the model has current_version set to 1
    $this->assertSame(1, $post->current_version);

    $post->update([
        'title' => 'Updated Title',
        'body' => 'Updated Body',
    ]);

    $this->assertSame(2, $post->current_version);

    // Act: Undo the last version
    Rewind::undo($post);

    // Assert: The model should be reverted to the previous version
    $this->assertSame(1, $post->current_version);
    $this->assertSame('Original Title', $post->title);
    $this->assertSame('Original Body', $post->body);

    // Act: Update the model again
    $post->update([
        'title' => 'Updated Title Again',
        'body' => 'Updated Body Again',
    ]);

    // Assert: The model should now be at version 3
    $this->assertSame(3, $post->current_version);
    $this->assertSame('Updated Title Again', $post->title);
    $this->assertSame('Updated Body Again', $post->body);
});

it('can jump to a specified version', function () {
    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'body' => 'Original Body',
    ]);

    $post->refresh();

    // Assert the model has current_version set to 1
    $this->assertSame(1, $post->current_version);

    $post->update([
        'title' => 'Updated Title',
        'body' => 'Updated Body',
    ]);

    $this->assertSame(2, $post->current_version);

    // Act: Jump to version 1
    Rewind::goToVersion($post, 1);

    // Assert: The model should be reverted to the previous version
    $this->assertSame(1, $post->current_version);
    $this->assertSame('Original Title', $post->title);
    $this->assertSame('Original Body', $post->body);

    // Act: Jump to version 2
    Rewind::goToVersion($post, 2);

    // Assert: The model should be back to the latest version
    $this->assertSame(2, $post->current_version);
    $this->assertSame('Updated Title', $post->title);
    $this->assertSame('Updated Body', $post->body);
});

it('throws an exception when jumping to a version that does not exist', function () {
    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'body' => 'Original Body',
    ]);

    $post->refresh();

    // Assert the model has current_version set to 1
    $this->assertSame(1, $post->current_version);

    // Act: Jump to version 2
    Rewind::goToVersion($post, 2);
})->throws(VersionDoesNotExistException::class);

it('creates a new version when running undo if record_rewinds is enabled', function () {
    app()->config->set('rewind.record_rewinds', true);

    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'body' => 'Original Body',
    ]);

    $post->refresh();

    // Assert the model has current_version set to 1
    $this->assertSame(1, $post->current_version);

    $post->update([
        'title' => 'Updated Title',
        'body' => 'Updated Body',
    ]);

    $this->assertSame(2, $post->current_version);

    // Act: Undo the last version
    Rewind::undo($post);

    // Assert: The model should be reverted to the previous version
    $this->assertSame(1, $post->current_version);
    $this->assertSame('Original Title', $post->title);
    $this->assertSame('Original Body', $post->body);

    // Assert: A new version should be created
    $this->assertSame(3, $post->versions()->max('version'));

    // Assert all versions are attributed to the user
    $this->assertSame(3, $post->versions()->where('user_id', $this->user->id)->count());

    // Assert each of the versions' user() relationship is the user
    $post->versions->load('user')->each(function ($version) {
        $this->assertSame($this->user->id, $version->user->id);
    });
});
