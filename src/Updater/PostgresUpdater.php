<?php

namespace WF\Batch\Updater;

use WF\Batch\Helpers\Alternate;
use WF\Batch\Settings;

final class PostgresUpdater implements Updater
{
    private string $keyCast;
    private static array $castTypes = [];
    private static array $casts = [
        'smallint'      => '::integer',
        'integer'       => '::integer',
        'bigint'        => '::integer',
        'decimal'       => '::float',
        'float'         => '::float',
        'boolean'       => '::boolean',
        'uuid'          => '::uuid',
        'guid'          => '::uuid',
        'datetime'      => '::timestamp',
        'datetimetz'    => '::timestamp',
        'date'          => '::date',
        'time'          => '::time',
        'json'          => '::json',
        'blob'          => '::bytea',
        'inet'          => '::inet',
        'macaddr'       => '::macaddr',
        'string'        => '::text',
        'text'          => '::text',
    ];

    public static function addCastMapping(string $type, string $castUsing) : void
    {
        self::$casts[$type] = $castUsing;
    }

    public function performUpdate(Settings $settings, string $column, array $values, array $ids) : void
    {
        if (1 === count($values)) {
            $settings->dbConnection
                ->table($settings->table)
                ->whereIn($settings->keyName, $ids)
                ->update([
                    $column => $values[0]
                ]);

            return;
        }

        $this->keyCast = in_array($settings->keyType, ['int', 'integer']) ? '::integer' : '::text';

        $settings->dbConnection->update(
            $this->sql($settings, $column, count($values)),
            Alternate::arrays($ids, $values)
        );
    }

    private function sql(Settings $settings, string $column, int $valuesCount) : string
    {
        $stringValues = implode(',', array_fill(0, $valuesCount, '(?, ?)'));
        return "update \"{$settings->table}\" as t
                set \"{$column}\" = (help_c.column_copy){$this->castTypeForColumn($settings, $column)}
                from (values {$stringValues}) as help_c(column_id, column_copy)
                where (help_c.column_id){$this->keyCast} = t.\"{$settings->keyName}\"{$this->keyCast}";
    }

    private function castTypeForColumn(Settings $settings, string $column) : string
    {
        if (! isset(self::$castTypes[$settings->table][$column])) {
            self::$castTypes[$settings->table][$column] = $this->castType(
                $settings->dbConnection
                    ->getSchemaBuilder()
                    ->getColumnType($settings->table, $column)
            );
        }
        return self::$castTypes[$settings->table][$column];
    }

    private function castType(string $type) : string
    {
        return self::$casts[$type] ?? '::text';
    }
}
