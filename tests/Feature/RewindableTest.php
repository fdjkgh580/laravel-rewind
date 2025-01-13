<?php

use AvocetShores\LaravelRewind\Models\RewindVersion;
use AvocetShores\LaravelRewind\Tests\Models\Post;
use AvocetShores\LaravelRewind\Tests\Models\PostWithRewindableAttributes;
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

it('creates a version when a model is created', function () {
    // Arrange: Ensure no versions exist
    $this->assertSame(0, RewindVersion::count());

    // Act: Create a Post
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Initial Title',
        'body' => 'This is the body content',
    ]);

    // Assert: One version should be created with the "old_values" mostly null,
    // and "new_values" reflecting the newly inserted record.
    $this->assertSame(1, RewindVersion::count());

    $version = RewindVersion::first();

    expect($version->new_values)->toMatchArray([
        'title' => 'Initial Title',
        'body' => 'This is the body content',
    ])
        ->and($version->old_values)->toMatchArray([
            'title' => null,
            'body' => null,
        ]);
    // Or empty array depending on your logic
});

it('creates a version when a model is updated', function () {
    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title' => 'Original Title',
        'body' => 'Original Body',
    ]);
    RewindVersion::query()->update(['created_at' => now()->subMinute()]);

    // Act: Update the post
    $post->title = 'Updated Title';
    $post->body = 'Updated Body';
    $post->save();

    // Assert: Versions should now be 2
    $this->assertSame(2, RewindVersion::count());

    $latestVersion = RewindVersion::orderBy('id', 'desc')->first();

    // Check that old and new values reflect the change
    expect($latestVersion->old_values)->toMatchArray([
        'title' => 'Original Title',
        'body' => 'Original Body',
    ])
        ->and($latestVersion->new_values)->toMatchArray([
            'title' => 'Updated Title',
            'body' => 'Updated Body',
        ]);
});

it('does not create a new version if nothing changes on save', function () {
    // Arrange
    $post = $this->user->posts()->create([
        'title' => 'No Change Title',
        'body' => 'No Change Body',
    ]);
    $originalVersionCount = RewindVersion::count();

    // Now retrieve post from the db and save it without making any changes
    $post = Post::find($post->id);
    $post->save();

    // Assert: Versions have not increased
    $this->assertSame($originalVersionCount, RewindVersion::count());
});

it('can track only specified attributes if $rewindable is defined', function () {
    // Here we assume that the Post model has:
    // protected $rewindable = ['title'];
    // so that body changes won't be recorded.

    // Arrange
    $post = PostWithRewindableAttributes::create([
        'user_id' => $this->user->id,
        'title' => 'Tracked Title',
        'body' => 'Untracked Body',
    ]);

    // Assert: One version should be created
    $this->assertSame(1, $post->versions()->count());

    // Act: Get new post from db and update only the body
    $post = PostWithRewindableAttributes::find($post->id);
    $post->body = 'Updated Body';
    $post->save();

    // Assert: No new version should be created if body is not in the $rewindable array
    $this->assertSame(1, $post->versions()->count());

    // Act again: Update the title (which is in $rewindable)
    $post->title = 'Changed Title';
    $post->save();

    // Assert: Now we should see a new version
    $this->assertSame(2, $post->versions()->count());
});

it('creates a version when a model is deleted (if we want to track deletions)', function () {
    // Arrange
    $post = Post::create([
        'user_id' => 1,
        'title' => 'Delete Me',
        'body' => 'Delete Body',
    ]);
    $this->assertSame(1, RewindVersion::count());

    // Act: Delete the model
    $post->delete();

    // Assert: Now we should have 2 versions in total, one for create, one for delete
    $this->assertSame(2, RewindVersion::count());
});

it('does not record a version if disableRewindEvents is set to true before saving', function () {
    // Sometimes we want to skip auto-creation of versions, e.g. reverts.

    // Arrange
    $post = Post::create([
        'user_id' => 1,
        'title' => 'First Title',
        'body' => 'First Body',
    ]);
    $this->assertSame(1, RewindVersion::count());

    // Act: Temporarily disable events and then update
    $post->disableRewindEvents();
    $post->title = 'Second Title';
    $post->save();

    // Assert: No new version was created
    $this->assertSame(1, RewindVersion::count());
});
