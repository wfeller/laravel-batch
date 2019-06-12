<?php

namespace WF\Batch\Updater;

use WF\Batch\BatchInsert;

interface Updater
{
    public function performUpdate(BatchInsert $insert, string $column, array $values, array $ids) : void;
}
