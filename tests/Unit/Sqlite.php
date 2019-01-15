<?php

namespace WF\Batch\Tests\Unit;

class Sqlite extends BaseTests
{
    protected function databaseDriver() : array
    {
        return [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ];
    }
}
