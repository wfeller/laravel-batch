<?php

namespace WF\Batch\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use WF\Batch\Updater\Updater;

class BatchException extends RuntimeException
{
    public static function notAnEloquentModel(string $class) : self
    {
        return new self("'{$class}' is not a '".Model::class."'.");
    }

    public static function batchSize(int $batchSize) : self
    {
        return new self("Batch size must be greater than 0 (got '{$batchSize}').");
    }

    public static function notAnUpdater(string $class) : self
    {
        return new self("'{$class}' is not an '".Updater::class."'.");
    }

    public static function noRegisteredUpdater(string $driver) : self
    {
        return new self("No registered updated for driver: '{$driver}'.");
    }

    public static function invalidClass(string $expected, string $actual) : self
    {
        return new self("Unexpected class '{$actual}', expected '{$expected}'.");
    }

    public static function inconsistentArraySizes(int $expected, int $actual) : self
    {
        return new self("Invalid array size '{$actual}', expected '{$expected}'.");
    }
}
