# Laravel Batch
## Save and update your eloquent models in batches

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wfeller/laravel-batch.svg?style=flat-square)](https://packagist.org/packages/wfeller/laravel-batch)
[![Total Downloads](https://img.shields.io/packagist/dt/wfeller/laravel-batch.svg?style=flat-square)](https://packagist.org/packages/wfeller/laravel-batch)
[![Plant Tree](https://img.shields.io/badge/dynamic/json?color=brightgreen&label=Plant%20Tree&query=%24.total&url=https%3A%2F%2Fpublic.offset.earth%2Fusers%2Ftreeware%2Ftrees)](https://plant.treeware.earth/wfeller/laravel-batch)
[![Buy us a tree](https://img.shields.io/badge/Treeware-%F0%9F%8C%B3-lightgreen?style=for-the-badge)](https://plant.treeware.earth/wfeller/laravel-batch)

This package allows you to save and update models in batch, meaning you can save or
update many models at the same time, and still fire your events as single saves or
updates do.

## Licence

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/wfeller/laravel-batch) to thank us for our work. By contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.

You can buy trees here [offset.earth/treeware](https://plant.treeware.earth/{vendor}/{package})

Read more about Treeware at [treeware.earth](http://treeware.earth)

## Installation

You can install the package via composer:

```bash
composer require wfeller/laravel-batch
```

## Usage

### Creating and updating models
``` php
use App\Car;
use WF\Batch\Batch;

$cars = [
    ['brand' => 'Audi', 'model' => 'A6'],
    ['brand' => 'Ford', 'model' => 'Mustang'],
    $myCar // an existing or new car instance
];

$carIds = Batch::of(Car::class, $cars)->save()->now();

// You can queue the batch
Batch::of(Car::class, $cars)->save()->dispatch();
Batch::of(Car::class, $cars)->save()->onQueue('other-queue')->dispatch();

// You can set the batch size based on your needs
Batch::of(Car::class, $cars)->batchSize(1000)->save()->now();
```

For the updates, there will be one DB query per updated column. For the saves, there will
only be one query per set of columns.

### Deleting Models

``` php
use App\Car;
use WF\Batch\Batch;

$cars = [
    1, // a car id
    $car, // a car instance
    ... // many more cars
];

$deletedIds = Batch::of(Car::class, $cars)->delete()->now();
Batch::of(Car::class, $cars)->delete()->dispatch();
Batch::of(Car::class, $cars)->delete()->onQueue('other-queue')->dispatch();
```

You'll have 1 query to delete your models. If you're passing model IDs, the models will be loaded from the DB to fire the deletion model events.

### If you want to create batches directly from your models:
``` php
class Car extends \Illuminate\Database\Eloquent\Model
{
    use \WF\Batch\Traits\Batchable;
    
    // ...
}

// This allows you to call
Car::newBatch($cars)->save()->now();
// which is the same as
Batch::of(Car::class, $cars)->save()->now();
```

### Benchmarks

**These benchmarks are not accurate, but they give some kind of rough idea of the potential performance improvement or usefulness of this package.**

The results vary a lot based on the DB driver, but basically that's what you get:
1. Laravel's bulk insert (this one doesn't fire model events though, the others do)
2. This package's Batch Saving (1.3 to 3 times slower than #1)
3. Laravel foreach create (8 to 50 times slower than #1)


* Laravel's bulk insert is the fastest, but doesn't fire model events.
``` php
User::insert([$userA, $userB, $userC]);
```

* This package's Batch Saving takes up to 3 times as long as Laravel's bulk insert, but your model events get fired
``` php
Batch::of(User::class, [$userA, $userB, $userC])->save()->now();
```

* 'Foreach create' is the slowest, taking at least 3 times longer than Batch Saving
``` php
$users = [$userA, $userB, $userC];
foreach ($users as $user) 
{
    User::create($user);
}
```

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email me instead of using the issue tracker.

## Credits

- [William](https://github.com/wfeller)
- [All Contributors](../../contributors)
