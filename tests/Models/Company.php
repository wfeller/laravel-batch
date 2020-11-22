<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use WF\Batch\Traits\Batchable;
use WF\Batch\Traits\RemembersBatchState;

class Company extends Model
{
    use Batchable, RemembersBatchState;

    protected $table = 'companies';

    protected $guarded = [];
}
