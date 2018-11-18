<?php

namespace Wfeller\Batch\Query;

use Wfeller\Batch\BatchInsert;

abstract class Driver
{
    /** @var \Wfeller\Batch\BatchInsert */
    protected $insert;

    public function __construct(BatchInsert $insert)
    {
        $this->insert = $insert;
    }

    abstract public function rawUpdate(string $column, string $stringValues) : string;
}
