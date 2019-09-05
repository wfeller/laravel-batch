<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class ModelWithoutBatchableTrait extends Model
{
    protected $table = 'not_batchables';
}
