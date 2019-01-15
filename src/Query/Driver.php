<?php

namespace WF\Batch\Query;

use WF\Batch\BatchInsert;

abstract class Driver
{
    protected $insert;

    public function __construct(BatchInsert $insert)
    {
        $this->insert = $insert;
    }

    public function performUpdate(string $column, array $values, array $ids) : void
    {
        $this->insert->connection->update(
            $this->sql($column, $values, $ids),
            $this->bindings($column, $values, $ids)
        );
    }

    abstract protected function sql(string $column, array $values, array $ids) : string;
    abstract protected function bindings(string $column, array $values, array $ids) : array;
}
