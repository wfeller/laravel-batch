<?php

namespace WF\Batch\Tests\Unit;

use WF\Batch\Tests\TestCase;

class Sqlite extends TestCase
{
    use SaveTests, DeleteTests;

    protected function databaseDriver() : array
    {
        return [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ];
    }
}
