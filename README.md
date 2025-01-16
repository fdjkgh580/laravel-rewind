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

// Oops! Let's revert
Rewind::undo($post);

// Need that change back?
Rewind::redo($post);
```

You can also view a list of previous versions of a model, see what changed, and even jump to a specific version.

```php
$versions = $post->versions;

Rewind::goTo($post, $versions->first()->id);
```

## How It Works

Under the hood, Rewind stores a combination of partial and full snapshots of your model’s data. The interval between 
full
snapshots is determined by the `rewind.snapshot_interval` config value. This provides you with a customizable trade-off 
between storage cost and performance.
Our engine will automatically determine the shortest path between your current version, available snapshots, and the 
target version when you call `Rewind::goTo($post, $versionId)`.

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

2. Undoing / Redoing with the Rewind Facade

```php
use AvocetShores\LaravelRewind\Facades\Rewind;

// Undo the most recent change (move back one version)
Rewind::undo($post);

// Redo the change (if you haven't modified the post in between)
Rewind::redo($post);

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
