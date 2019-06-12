<?php

namespace WF\Batch\Updater;

use WF\Batch\BatchInsert;
use WF\Batch\Helpers\Alternate;

final class GenericUpdater implements Updater
{
    public function performUpdate(BatchInsert $insert, string $column, array $values, array $ids) : void
    {
        $insert->dbConnection->update(
            $this->sql($insert, $column, count($values)),
            array_merge(Alternate::arrays($ids, $values), $ids)
        );
    }

    private function sql(BatchInsert $insert, string $column, int $valuesCount) : string
    {
        $stringValues = array_fill(0, $valuesCount, "WHEN `{$insert->settings->keyName}` = ? THEN ?");
        $stringValues = implode(' ', $stringValues);
        $stringIds = implode(',', array_fill(0, $valuesCount, '?'));

        return "UPDATE `{$insert->settings->table}` SET `{$column}` = CASE {$stringValues} END
                WHERE `{$insert->settings->keyName}` IN ({$stringIds})";
    }
}
