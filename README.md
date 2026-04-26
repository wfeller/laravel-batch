# Laravel Batch

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wfeller/laravel-batch.svg?style=flat-square)](https://packagist.org/packages/wfeller/laravel-batch)
[![Total Downloads](https://img.shields.io/packagist/dt/wfeller/laravel-batch.svg?style=flat-square)](https://packagist.org/packages/wfeller/laravel-batch)
[![Plant Tree](https://img.shields.io/badge/dynamic/json?color=brightgreen&label=Plant%20Tree&query=%24.total&url=https%3A%2F%2Fpublic.offset.earth%2Fusers%2Ftreeware%2Ftrees)](https://plant.treeware.earth/wfeller/laravel-batch)
[![Buy us a tree](https://img.shields.io/badge/Treeware-%F0%9F%8C%B3-lightgreen?style=for-the-badge)](https://plant.treeware.earth/wfeller/laravel-batch)

Save and update your Eloquent models in batches while still firing model events.

## Introduction

This package allows you to efficiently save, update, and delete many Eloquent models at once while maintaining Laravel's standard model event system. Unlike Laravel's native `insert()` method, this package fires all the normal model events (`saving`, `saved`, `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`).

## Installation

You can install the package via composer:

```bash
composer require wfeller/laravel-batch
```

## Quick Start

```php
use App\Models\User;
use WF\Batch\Batch;

// Save multiple models at once
$users = [
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
    $existingUser // existing model instance
];

$userIds = Batch::of(User::class, $users)->save()->now();
```

## Core Features

### 1. Batch Saving Models

Save multiple models with a single operation:

```php
use App\Models\Car;
use WF\Batch\Batch;

$cars = [
    ['brand' => 'Audi', 'model' => 'A6'],
    ['brand' => 'Ford', 'model' => 'Mustang'],
    $existingCar // existing model instance
];

// Save immediately
$carIds = Batch::of(Car::class, $cars)->save()->now();

// Set custom batch size
$carIds = Batch::of(Car::class, $cars)->batchSize(100)->save()->now();
```

### 2. Batch Updating Models

Update multiple existing models:

```php
use App\Models\User;
use WF\Batch\Batch;

$users = [
    ['id' => 1, 'name' => 'Updated John'],
    ['id' => 2, 'name' => 'Updated Jane'],
    $userInstance // existing model with changes
];

$updatedIds = Batch::of(User::class, $users)->save()->now();
```

### 3. Batch Deleting Models

Delete multiple models efficiently:

```php
use App\Models\Car;
use WF\Batch\Batch;

// Delete by IDs
$carIds = [1, 2, 3, 5, 8];
$deletedIds = Batch::of(Car::class, $carIds)->delete()->now();

// Delete by model instances
$cars = Car::find([1, 2, 3]);
$deletedIds = Batch::of(Car::class, $cars)->delete()->now();

// Mixed approach
$mixed = [1, $carInstance, 3, $anotherCar];
$deletedIds = Batch::of(Car::class, $mixed)->delete()->now();
```

### 4. Queue Support

Process batch operations in the background:

```php
use App\Models\User;
use WF\Batch\Batch;

$users = [
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
];

// Dispatch to default queue
Batch::of(User::class, $users)->save()->dispatch();

// Dispatch to specific queue
Batch::of(User::class, $users)->save()->onQueue('high-priority')->dispatch();
```

### 5. Model Trait Integration

Add batch functionality directly to your models:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use WF\Batch\Traits\Batchable;

class Car extends Model
{
    use Batchable;
    
    // ...
}

// Now you can use batch operations directly on the model
$cars = [
    ['brand' => 'Audi', 'model' => 'A6'],
    ['brand' => 'Ford', 'model' => 'Mustang']
];

// These are equivalent
$carIds = Car::newBatch($cars)->save()->now();
$carIds = Car::batchSave($cars);

// For deletion
Car::batchDelete([1, 2, 3]);
```

### 6. Batch Size Configuration

Control how many models are processed in each batch:

```php
use App\Models\User;
use WF\Batch\Batch;

$users = collect()->range(1, 10000)->map(fn($i) => [
    'name' => "User $i",
    'email' => "user$i@example.com"
]);

// Process in batches of 500 (default)
Batch::of(User::class, $users)->save()->now();

// Process in batches of 1000
Batch::of(User::class, $users)->batchSize(1000)->save()->now();

// Set global default batch size
Batch::setDefaultBatchSize(1000);
```

## Performance Comparison

| Method | Speed | Model Events |
|--------|-------|--------------|
| Laravel's `insert()` | Fastest | No |
| Laravel Batch | 1.3-3x slower than `insert()` | Yes |
| Individual `create()` calls | 8-50x slower than `insert()` | Yes |

```php
// Fastest but no events
User::insert([$userA, $userB, $userC]);

// Balanced: good performance with events
Batch::of(User::class, [$userA, $userB, $userC])->save()->now();

// Slowest: individual operations
foreach ([$userA, $userB, $userC] as $user) {
    User::create($user);
}
```

## Event Handling

All standard Laravel model events are fired during batch operations:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected static function booted()
    {
        static::creating(function (User $user) {
            // Called for each new user in batch
            $user->created_by = auth()->id();
        });
        
        static::updating(function (User $user) {
            // Called for each updated user in batch
            $user->updated_by = auth()->id();
        });
        
        static::deleting(function (User $user) {
            // Called for each user being deleted in batch
            $user->deleted_by = auth()->id();
        });
    }
}
```

## Testing

Run the test suite:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email me instead of using the issue tracker.

## License

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/wfeller/laravel-batch) to thank us for our work. By contributing to the Treeware forest you'll be creating employment for local families and restoring wildlife habitats.

You can buy trees here [offset.earth/treeware](https://plant.treeware.earth/{vendor}/{package})

Read more about Treeware at [treeware.earth](http://treeware.earth)

## Credits

- [William](https://github.com/wfeller)
- [All Contributors](../../contributors)
