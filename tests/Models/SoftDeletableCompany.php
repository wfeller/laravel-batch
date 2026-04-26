<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WF\Batch\Traits\Batchable;

class SoftDeletableCompany extends Model
{
    use Batchable, SoftDeletes;

    protected $table = 'soft_deletable_companies';

    protected $guarded = [];
}
