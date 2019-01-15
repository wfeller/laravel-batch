<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;

class Settings
{
    public $table;
    public $keyName;
    public $keyType;
    public $usesTimestamps;
    public $columns;

    public function __construct(Model $model)
    {
        $this->table = $model->getTable();
        $this->keyName = $model->getKeyName();
        $this->keyType = $model->getKeyType();
        $this->usesTimestamps = $model->usesTimestamps();

        $this->columns = array_filter(
            $model->getConnection()->getSchemaBuilder()->getColumnListing($this->table),
            function ($value) {
                return $value !== $this->keyName;
            }
        );
    }
}
