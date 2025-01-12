<?php

use AvocetShores\LaravelRewind\Facades\Rewind;
use AvocetShores\LaravelRewind\Models\RewindRevision;
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

it('creates a revision when a model is created', function () {
    // Arrange: Ensure no revisions exist
    $this->assertSame(0, RewindRevision::count());

    // Act: Create a Post
    $post = Post::create([
        'user_id' => $this->user->id,
        'title'   => 'Initial Title',
        'body'    => 'This is the body content',
    ]);

    // Assert: One revision should be created with the "old_values" mostly null,
    // and "new_values" reflecting the newly inserted record.
    $this->assertSame(1, RewindRevision::count());

    $revision = RewindRevision::first();

    expect($revision->new_values)->toMatchArray([
        'title' => 'Initial Title',
        'body' => 'This is the body content',
    ])
        ->and($revision->old_values)->toMatchArray([
            'title' => null,
            'body' => null,
        ]);
    // Or empty array depending on your logic
});

it('creates a revision when a model is updated', function () {
    // Arrange
    $post = Post::create([
        'user_id' => $this->user->id,
        'title'   => 'Original Title',
        'body'    => 'Original Body',
    ]);
    RewindRevision::query()->update(['created_at' => now()->subMinute()]);

    // Act: Update the post
    $post->title = 'Updated Title';
    $post->body = 'Updated Body';
    $post->save();

    // Assert: Revisions should now be 2
    $this->assertSame(2, RewindRevision::count());

    $latestRevision = RewindRevision::orderBy('id', 'desc')->first();

    // Check that old and new values reflect the change
    expect($latestRevision->old_values)->toMatchArray([
        'title' => 'Original Title',
        'body' => 'Original Body',
    ])
        ->and($latestRevision->new_values)->toMatchArray([
            'title' => 'Updated Title',
            'body' => 'Updated Body',
        ]);
});

it('does not create a new revision if nothing changes on save', function () {
    // Arrange
    $post = $this->user->posts()->create([
        'title'   => 'No Change Title',
        'body'    => 'No Change Body',
    ]);
    $originalRevisionCount = RewindRevision::count();

    // Now retrieve post from the db and save it without making any changes
    $post = Post::find($post->id);
    $post->save();

    // Assert: Revisions have not increased
    $this->assertSame($originalRevisionCount, RewindRevision::count());
});

it('can track only specified attributes if $rewindable is defined', function () {
    // Here we assume that the Post model has:
    // protected $rewindable = ['title'];
    // so that body changes won't be recorded.

    // Arrange
    $post = PostWithRewindableAttributes::create([
        'user_id' => $this->user->id,
        'title'   => 'Tracked Title',
        'body'    => 'Untracked Body',
    ]);

    // Assert: One revision should be created
    $this->assertSame(1, $post->revisions()->count());

    // Act: Get new post from db and update only the body
    $post = PostWithRewindableAttributes::find($post->id);
    $post->body = 'Updated Body';
    $post->save();

    // Assert: No new revision should be created if body is not in the $rewindable array
    $this->assertSame(1, $post->revisions()->count());

    // Act again: Update the title (which is in $rewindable)
    $post->title = 'Changed Title';
    $post->save();

    // Assert: Now we should see a new revision
    $this->assertSame(2, $post->revisions()->count());
});

it('creates a revision when a model is deleted (if we want to track deletions)', function () {
    // Arrange
    $post = Post::create([
        'user_id' => 1,
        'title'   => 'Delete Me',
        'body'    => 'Delete Body',
    ]);
    $this->assertSame(1, RewindRevision::count());

    // Act: Delete the model
    $post->delete();

    // Assert: Now we should have 2 revisions in total, one for create, one for delete
    $this->assertSame(2, RewindRevision::count());
});

it('does not record a revision if disableRewindEvents is set to true before saving', function () {
    // Sometimes we want to skip auto-creation of revisions, e.g. reverts.

    // Arrange
    $post = Post::create([
        'user_id' => 1,
        'title'   => 'First Title',
        'body'    => 'First Body',
    ]);
    $this->assertSame(1, RewindRevision::count());

    // Act: Temporarily disable events and then update
    $post->disableRewindEvents = true;
    $post->title = 'Second Title';
    $post->save();

    // Assert: No new revision was created
    $this->assertSame(1, RewindRevision::count());
});
