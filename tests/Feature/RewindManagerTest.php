<?php

use AvocetShores\LaravelRewind\Facades\Rewind;
use AvocetShores\LaravelRewind\Tests\Models\Post;
use AvocetShores\LaravelRewind\Tests\Models\User;

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

it('rewinds a model to the previous revision on undo', function () {
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

    // Act: Undo the last revision
    Rewind::undo($post);

    // Assert: The model should be reverted to the previous revision
    $this->assertSame(1, $post->current_version);
    $this->assertSame('Original Title', $post->title);
    $this->assertSame('Original Body', $post->body);
});
