<?php

namespace WF\Batch\Helpers;

use WF\Batch\Exceptions\BatchException;

class Alternate
{
    /**
     * Interleaves values from multiple arrays: Alternate::arrays([1,2], [10,20])
     * returns [1, 10, 2, 20]. Used to build CASE WHEN bindings where each
     * clause needs (id, value) pairs in sequence. All arrays must be the
     * same size.
     *
     * @param  array[] $arrays
     * @return array
     */
    public static function arrays(array... $arrays) : array
    {
        $first = array_shift($arrays);
        $count = count($first);
        $allReversed = [];

        foreach (array_reverse($arrays) as $array) {
            if ($count !== count($array)) {
                throw BatchException::inconsistentArraySizes($count, count($array));
            }

            $allReversed[] = $array;
        }

        $final = [];

        foreach (array_reverse($first) as $value) {
            foreach ($allReversed as &$array) {
                $final[] = array_pop($array);
            }

            $final[] = $value;
        }

        return array_reverse($final);
    }
}
