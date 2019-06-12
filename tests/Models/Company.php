<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use WF\Batch\Traits\Batchable;

class Company extends Model
{
    use Batchable;

    protected $table = 'companies';

    protected $guarded = [];
}
