<?php

namespace WF\Batch\Tests\Unit;

use WF\Batch\Exceptions\BatchException;
use WF\Batch\Helpers\Alternate;
use WF\Batch\Tests\TestCase;

class AlternateTest extends TestCase
{
    protected function databaseDriver() : array
    {
        return [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ];
    }

    public function test_values_are_correctly_alternated()
    {
        $arrays = [
            [1, 5, 9],
            [2, 6, 10],
            [3, 7, 11],
            [4, 8, 12],
        ];

        $expected = [1,2,3,4,5,6,7,8,9,10,11,12];

        $this->assertEquals($expected, Alternate::arrays(...$arrays));
    }

    public function test_throws_if_arrays_dont_have_the_same_number_of_items()
    {
        $this->expectException(BatchException::class);
        Alternate::arrays([1], [2], [3], [4, 5], [6]);
    }
}
