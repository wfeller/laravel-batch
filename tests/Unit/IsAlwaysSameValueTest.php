<?php

namespace WF\Batch\Tests\Unit;

use WF\Batch\Updater\HandlesUniqueValueUpdates;
use WF\Batch\Tests\TestCase;

class IsAlwaysSameValueTest extends TestCase
{
    use HandlesUniqueValueUpdates {
        isAlwaysSameValue as public;
    }

    protected function databaseDriver() : array
    {
        return [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ];
    }

    public function test_returns_true_when_all_values_are_identical()
    {
        $this->assertTrue($this->isAlwaysSameValue(['a', 'a', 'a']));
    }

    public function test_returns_false_when_values_differ()
    {
        $this->assertFalse($this->isAlwaysSameValue(['a', 'b', 'a']));
    }

    public function test_returns_true_for_single_value()
    {
        $this->assertTrue($this->isAlwaysSameValue(['only']));
    }

    public function test_returns_true_for_empty_array()
    {
        $this->assertTrue($this->isAlwaysSameValue([]));
    }

    public function test_returns_true_when_all_values_are_null()
    {
        $this->assertTrue($this->isAlwaysSameValue([null, null, null]));
    }

    public function test_returns_false_when_first_value_is_null_and_others_differ()
    {
        $this->assertFalse($this->isAlwaysSameValue([null, 'foo']));
    }

    public function test_returns_false_when_null_mixed_with_other_values()
    {
        $this->assertFalse($this->isAlwaysSameValue(['a', null, 'a']));
    }
}
