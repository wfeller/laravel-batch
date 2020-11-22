<?php

namespace WF\Batch\Events;

use Illuminate\Database\Eloquent\Model;

class BatchDeleted
{
    public Model $model;
    public array $modelKeys;

    public function __construct(Model $model, array $modelKeys)
    {
        $this->model = $model;
        $this->modelKeys = $modelKeys;
    }
}
