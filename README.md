# Laravel Rewind

[![Latest Version on Packagist](https://img.shields.io/packagist/v/avocet-shores/laravel-rewind.svg?style=flat-square)](https://packagist.org/packages/avocet-shores/laravel-rewind)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/avocet-shores/laravel-rewind/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/avocet-shores/laravel-rewind/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/avocet-shores/laravel-rewind/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/avocet-shores/laravel-rewind/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/avocet-shores/laravel-rewind.svg?style=flat-square)](https://packagist.org/packages/avocet-shores/laravel-rewind)

Laravel Rewind is a simple, opinionated package that provides versioning, undo, and redo functionality for your 
Eloquent models.

Imagine you have a Post model and want to track how the title and body evolve over time. With Rewind, you can do this in a few lines:

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

Rewind::goToVersion($post, $versions->first()->id);
```

## Features

- Track all or specific attributes on any of your models.
- Automatically log old and new values in a dedicated “rewind_versions” table.
- Easily undo or redo changes.
- Optionally store your model’s current version for full undo/redo capabilities.
- Access a version audit log for each model to see every recorded version.

## Installation

You can install the package via composer:

```bash
composer require avocet-shores/laravel-rewind
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-rewind-migrations"
php artisan migrate
```

You can (optionally) publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-rewind-config"
```

## Getting Started

To enable version tracking on a model:

### 1. Add the Rewindable trait to your Eloquent model:

```php
use AvocetShores\LaravelRewind\Concerns\Rewindable;

class Post extends Model
{
   use Rewindable;

   // Option A: Track specific attributes
   protected $rewindable = ['title', 'body'];
   
   // Option B: Track all attributes
   // protected $rewindAll = true;
}
```

### 2. (Optional) For Full Redo Support:

If you’d like to jump forward to future versions, you’ll need a way to track which version your model is 
currently on. By default, Rewindable does not store a current version on your model’s table. To add it, you can use our 
convenient artisan command to generate a migration:

```bash
php artisan rewind:add-version
```

- This command will prompt you for the table name you wish to extend.  
- After providing the table name, it creates a migration file that adds a current_version column to that table.
- Run `php artisan migrate` to apply it.  
- Once this column is in place, the RewindManager will automatically manage your model’s current_version, allowing 
  proper undo/redo flows.

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
Rewind::goToVersion($post, 5);
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

- [jared.cannon](https://github.com/jared-cannon)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
