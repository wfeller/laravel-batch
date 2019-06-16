<?php

namespace WF\Batch\Traits;

use WF\Batch\Batch;

trait Batchable
{
    public static function batch(iterable $models) : Batch
    {
        return new Batch($models, static::class);
    }

    /**
     * @param array  $models
     * @param int    $batchSize
     * @return array The ids that were just saved (if available).
     */
    public static function batchSave(iterable $models, int $batchSize = 500) : array
    {
        return static::batch($models)->batchSize($batchSize)->save()->now();
    }

    public static function batchSaveQueue(array $models, int $chunkSize = 500, string $queue = null) : void
    {
        static::batch($models)->batchSize($chunkSize)->save()->onQueue($queue)->dispatch();
    }

    /**
     * @param array  $models
     * @param int    $batchSize
     * @return array The ids of the models that were deleted.
     */
    public static function batchDelete(array $models, int $batchSize = 500) : array
    {
        return static::batch($models)->batchSize($batchSize)->delete()->now();
    }

    public static function batchDeleteQueue(array $models, int $batchSize = 500, string $queue = null) : void
    {
        static::batch($models)->batchSize($batchSize)->delete()->onQueue($queue)->dispatch();
    }
}
