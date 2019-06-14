<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;

class Settings
{
    private $model;
    private $columns = null;

    public $table;
    public $keyName;
    public $keyType;
    public $usesTimestamps;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->table = $model->getTable();
        $this->keyName = $model->getKeyName();
        $this->keyType = $model->getKeyType();
        $this->usesTimestamps = $model->usesTimestamps();
    }

    public function getColumns() : array
    {
        if (null === $this->columns) {
            $this->columns = array_filter(
                $this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->table),
                function ($value) {
                    return $value !== $this->keyName;
                }
            );
        }

        return $this->columns;
    }
}
