# Laravel Rewind

[![Latest Version on Packagist](https://img.shields.io/packagist/v/avocet-shores/laravel-rewind.svg?style=flat-square)](https://packagist.org/packages/avocet-shores/laravel-rewind)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/avocet-shores/laravel-rewind/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/avocet-shores/laravel-rewind/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Coverage Status](https://img.shields.io/codecov/c/github/avocet-shores/laravel-rewind?style=flat-square)](https://app.codecov.io/gh/avocet-shores/laravel-rewind/)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/avocet-shores/laravel-rewind/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/avocet-shores/laravel-rewind/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/avocet-shores/laravel-rewind.svg?style=flat-square)](https://packagist.org/packages/avocet-shores/laravel-rewind)

Laravel Rewind is a powerful, easy-to-use versioning package for your Eloquent models.

Imagine you have a Post model and want to track how it evolves over time:

```php
use AvocetShores\LaravelRewind\Facades\Rewind;

// Update the post, creating a new version
$post->title = 'Updated Title';
$post->save();

// Let's go back in time
Rewind::rewind($post);

// Or fast-forward
Rewind::fastForward($post);
```

You can also view a list of previous versions of a model, see what changed, and even jump to a specific version.

```php
$versions = $post->versions;

Rewind::goTo($post, $versions->first()->id);
```

## How It Works

Under the hood, Rewind stores a combination of partial and full snapshots of your model’s data. The interval between 
full snapshots is determined by the `rewind.snapshot_interval` config value. This provides you with a customizable trade-off 
between storage cost and performance. Our engine will automatically determine the shortest path between your current version, available snapshots, and the 
target version when you call `Rewind::goTo($post, $versionId)`.

### How does Rewind handle history and branching?

Rewind tries to maintain a simple linear history of your model’s changes. But what happens when you update a model 
while on an older version? Let's take a look:

1. You create a new version of your model.

    ```php
    // Previous title: 'Old Title'
    $post->title = 'New Title';
    $post->save();
    ```

2. You rewind to a previous version.

    ```php
    // Title goes back to 'Old Title'
    Rewind::rewind($post);
    ```

3. You update the model *while on an older version*.

   ```php
   $post->title = 'Rewind is Awesome!';
   $post->save();
   ```

4. What is the current version, and what's in it?

    In order to maintain a linear, non-destructive history, Rewind uses the previous head version as the 
    content for `old_values` for the new version you just created, while creating a full snapshot of the model’s current state and making it the new 
    head. So the current version looks like this:

    ```php
    [
        'version' => 3,
        'old_values' => [
            'title' => 'New Title',
            // Other attributes...
        ],
        'new_values' => [
            'title' => 'Rewind is Awesome!',
            // Other attributes...
        ],
    ]
    ```

In other words, your model's history always looks like you updated from the highest version. This way, you can always see 
what changed between versions, even if you jump back and forth in time. And you can always revert to a previous version without losing any data.

## Installation

You can install the package via composer:

```bash
composer require avocet-shores/laravel-rewind
```

You can publish and run the migrations, and publish the config, with:

```bash
sail artisan vendor:publish --provider="AvocetShores\LaravelRewind\LaravelRewindServiceProvider"

php artisan migrate
```

## Getting Started

To enable version tracking on a model:

### 1. Add the Rewindable trait to your Eloquent model:

```php
use AvocetShores\LaravelRewind\Concerns\Rewindable;

class Post extends Model
{
   use Rewindable;
}
```

### 2. Add the current_version column to your model’s table (optional):

To unlock the full power of Rewind, you’ll need a way to track which version your model is 
currently on. By default, Rewindable does not store a current version on your model’s table. To add it, you can use our 
convenient artisan command to generate a migration:

```bash
php artisan rewind:add-version
```

- This command will prompt you for the table name you wish to extend.  
- After providing the table name, it creates a migration file that adds a current_version column to that table.
- Run `php artisan migrate` to apply it.  
- Once this column is in place, the RewindManager will automatically manage your model’s current_version.

That’s it! Now your model’s changes are recorded in the `rewind_versions` table, and you can jump backwards or forwards in time.

## Usage

1. Updating a Model

    ```php
    $post = Post::find(1);
    $post->title = "New Title";
    $post->save();  
    // A new version is automatically created
    ```

2. Using the Rewind Facade

    ```php
    use AvocetShores\LaravelRewind\Facades\Rewind;

    // Rewind two versions back
    Rewind::rewind($post, 2);

    // Fast-forward one version
    Rewind::fastForward($post);

    // Jump directly to a specific version
    Rewind::goTo($post, 5);
    ```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jared Cannon](https://github.com/jared-cannon)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
