<?php

namespace WF\Batch\Query;

use WF\Batch\Helpers\Alternate;

class GenericDriver extends Driver
{
    protected function sql(string $column, array $values, array $ids) : string
    {
        $stringValues = array_fill(0, count($values), "WHEN `{$this->insert->settings->keyName}` = ? THEN ?");
        $stringValues = implode(' ', $stringValues);
        $stringIds = implode(',', array_fill(0, count($values), '?'));

        return "UPDATE `{$this->insert->settings->table}` SET `{$column}` = CASE {$stringValues} END
                WHERE `{$this->insert->settings->keyName}` IN ({$stringIds})";
    }

    protected function bindings(string $column, array $values, array $ids) : array
    {
        return array_merge(Alternate::arrays($ids, $values), $ids);
    }
}
