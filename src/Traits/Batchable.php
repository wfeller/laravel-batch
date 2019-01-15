<?php

namespace WF\Batch\Traits;

use WF\Batch\BatchInsert;

trait Batchable
{
    /**
     * @param array $models
     * @param int   $chunkSize
     * @return array The ids that were just saved (if available).
     */
    public static function batchSave(array $models, int $chunkSize = 250) : array
    {
        return (new BatchInsert($models, $chunkSize, static::class))->handle();
    }

    public static function batchSaveQueue(array $models, int $chunkSize = 250, string $queue = null) : void
    {
        dispatch(new BatchInsert($models, $chunkSize, static::class))->onQueue($queue);
    }
}
