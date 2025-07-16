<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use WF\Batch\Traits\Batchable;
use WF\Batch\Traits\RemembersBatchState;

class Car extends Model
{
    use Batchable, RemembersBatchState;

    protected $guarded = [];

    protected $primaryKey = 'big_increments';

    protected $casts = [
        'big_increments' => 'integer',
        'big_integer' => 'integer',
        'binary' => 'string',
        'boolean' => 'boolean',
        'char' => 'string',
        'decimal' => 'decimal:2',
        'double' => 'decimal:2',
        // enum
        'float' => 'decimal:2',
        // geometry
        // geometry_collection
        'integer' => 'integer',
        'ip_address' => 'string',
        'json' => 'array',
        'jsonb' => 'array',
        // 'line_string',
        'long_text' => 'string',
        'mac_address' => 'string',
        'medium_integer' => 'integer',
        'medium_text' => 'string',
        // 'multi_line_string',
        // multi_point
        // multi_polygon
        // point
        // polygon
        'small_integer' => 'integer',
        'string' => 'string',
        'text' => 'string',
        'time' => 'string',
        'time_tz' => 'string',
        'tiny_integer' => 'boolean',
        'unsigned_integer' => 'integer',
        'unsigned_big_integer' => 'integer',
        'unsigned_medium_integer' => 'integer',
        'unsigned_small_integer' => 'integer',
        'unsigned_tiny_integer' => 'boolean',
        'uuid' => 'string',
        'year' => 'integer',
    ];
}
