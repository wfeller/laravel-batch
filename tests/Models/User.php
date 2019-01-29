<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use WF\Batch\Traits\Batchable;

class User extends Model
{
    use Batchable;

    protected $guarded = [];

    protected $table = 'just_a_table';

    protected static function boot()
    {
        parent::boot();

        static::creating(function (User $user) {
            $user->event = 'creating';
        });
    }
}
