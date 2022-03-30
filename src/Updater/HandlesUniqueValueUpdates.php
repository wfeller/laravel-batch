<?php

namespace WF\Batch\Updater;

use WF\Batch\Settings;

trait HandlesUniqueValueUpdates
{
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
        foreach ($values as $value) {
            if (! isset($check)) {
                $check = $value;
                continue;
            }

            if ($check !== $value) {
                return false;
            }
        }

        return true;
    }
}
