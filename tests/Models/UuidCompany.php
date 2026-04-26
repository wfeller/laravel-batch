<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use WF\Batch\Traits\Batchable;

class UuidCompany extends Model
{
    use Batchable, HasUuids;

    protected $table = 'uuid_companies';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;
}
