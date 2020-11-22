<?php

namespace WF\Batch\Events;

use Illuminate\Database\Eloquent\Model;

class BatchDeleted
{
    public Model $model;
    public array $modelKeys;
    public bool $forceDeleting;

    public function __construct(Model $model, array $modelKeys, bool $forceDeleting)
    {
        $this->model = $model;
        $this->modelKeys = $modelKeys;
        $this->forceDeleting = $forceDeleting;
    }
}
