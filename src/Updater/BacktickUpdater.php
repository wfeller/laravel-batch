<?php

namespace WF\Batch\Updater;

use WF\Batch\Helpers\Alternate;
use WF\Batch\Settings;

final class BacktickUpdater implements Updater
{
    use HandlesUniqueValueUpdates;

    public function performUpdate(Settings $settings, string $column, array $values, array $ids) : void
    {
        if ($this->isAlwaysSameValue($values)) {
            $this->updateUsingWhereInQuery($settings, $column, $ids, $values[0]);

            return;
        }

        /**
         * Bindings: Alternate::arrays interleaves [id1, val1, id2, val2, ...]
         * for the CASE WHEN clauses, then $ids is appended for the WHERE IN.
         */
        $settings->dbConnection->update(
            $this->sql($settings, $column, count($values)),
            array_merge(Alternate::arrays($ids, $values), $ids)
        );
    }

    private function sql(Settings $settings, string $column, int $valuesCount) : string
    {
        $stringValues = array_fill(0, $valuesCount, "when `{$settings->keyName}` = ? then ?");
        $stringValues = implode(' ', $stringValues);
        $stringIds = implode(',', array_fill(0, $valuesCount, '?'));

        return "update `{$settings->table}` set `{$column}` = case {$stringValues} end
                where `{$settings->keyName}` in ({$stringIds})";
    }
}
