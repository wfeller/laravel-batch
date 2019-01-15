<?php

namespace WF\Batch\Helpers;

use WF\Batch\Exceptions\BatchInsertException;

class Alternate
{
    /**
     * Flattens the arrays and alternates the values of each array given: Arr::alternate($first, $second, $third)
     * will return [$first[0], $second[0], $third[0], $first[1], $second[1], $third[1], ...]
     * All arrays must have the same size.
     *
     * @param array[] $arrays
     * @return array
     */
    public static function arrays(array... $arrays) : array
    {
        $first = array_shift($arrays);
        $count = count($first);
        $allReversed = [];
        foreach (array_reverse($arrays) as $array) {
            if ($count !== count($array)) {
                throw new BatchInsertException('Invalid number of items.');
            }
            $allReversed[] = $array;
        }
        $final = [];
        array_map(function ($value) use (&$allReversed, &$final) {
            foreach ($allReversed as $key => $array) {
                $final[] = array_pop($array);
                $allReversed[$key] = $array;
            }
            $final[] = $value;
        }, array_reverse($first));
        return array_reverse($final);
    }
}