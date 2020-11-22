<?php

namespace WF\Batch\Events;

use Illuminate\Database\Eloquent\Model;

class BatchDeleting
{
    /** @var array|Model[] */
    public array $models;

    public function __construct(Model ... $models)
    {
        $this->models = $models;
    }
}
