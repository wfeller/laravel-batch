<?php

namespace Wfeller\Batch\Traits;

use Wfeller\Batch\BatchInsert;

trait Batchable
{
    /**
     * @param array $models
     * @param int   $chunkSize
     * @return array The ids that were just saved (if available).
     */
    public static function batchSave(array $models, int $chunkSize = 250) : array
    {
        $insert = new BatchInsert($models, $chunkSize, static::class);
        return $insert->run();
    }
}
