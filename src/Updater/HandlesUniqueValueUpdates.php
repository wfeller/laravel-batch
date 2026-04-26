<?php

namespace WF\Batch\Updater;

use WF\Batch\Settings;

trait HandlesUniqueValueUpdates
{
    /**
     * Optimization: when all models set the same value for a column,
     * we can use a simple UPDATE ... WHERE IN instead of the more
     * expensive CASE/WHEN approach.
     */
    protected function updateUsingWhereInQuery(Settings $settings, string $column, array $ids, $value) : void
    {
        $settings->dbConnection
            ->table($settings->table)
            ->whereIn($settings->keyName, $ids)
            ->update([
                $column => $value
            ]);
    }

    protected function isAlwaysSameValue(array $values) : bool
    {
        $first = true;

        foreach ($values as $value) {
            if ($first) {
                $check = $value;
                $first = false;
                continue;
            }

            if ($check !== $value) {
                return false;
            }
        }

        return true;
    }
}
