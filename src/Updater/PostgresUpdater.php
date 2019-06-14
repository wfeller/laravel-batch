<?php

namespace WF\Batch\Updater;

use Doctrine\DBAL\PostgresTypes as Types;
use Doctrine\DBAL\Types\Type;
use WF\Batch\BatchInsert;
use WF\Batch\Exceptions\BatchInsertException;
use WF\Batch\Helpers\Alternate;

final class PostgresUpdater implements Updater
{
    private static $castTypes = [];
    private $keyCast;
    private static $rareTypesRegistered = false;

    public function performUpdate(BatchInsert $insert, string $column, array $values, array $ids) : void
    {
        $this->keyCast = in_array($insert->settings->keyType, ['int', 'integer']) ? '::integer' : '::text';

        if (self::$rareTypesRegistered) {
            $this->initializeRareTypes($insert);
        }

        $insert->dbConnection->update(
            $this->sql($insert, $column, count($values)),
            Alternate::arrays($ids, $values)
        );
    }

    private function sql(BatchInsert $insert, string $column, int $valuesCount) : string
    {
        $stringValues = implode(',', array_fill(0, $valuesCount, '(?, ?)'));
        return "UPDATE \"{$insert->settings->table}\" AS t
                SET \"{$column}\" = (help_c.column_copy){$this->castTypeForColumn($column)}
                FROM (VALUES {$stringValues}) AS help_c(column_id, column_copy)
                WHERE (help_c.column_id){$this->keyCast} = t.\"{$insert->settings->keyName}\"{$this->keyCast}";
    }

    private function castTypeForColumn(BatchInsert $insert, string $column) : string
    {
        if (! isset(self::$castTypes[$insert->settings->table][$column])) {
            self::$castTypes[$insert->settings->table][$column] = $this->castType(
                $insert->dbConnection
                    ->getSchemaBuilder()
                    ->getColumnType($insert->settings->table, $column)
            );
        }
        return self::$castTypes[$insert->settings->table][$column];
    }

    private function castType(string $type) : string
    {
        switch ($type) {
            case 'smallint':
            case 'integer':
            case 'bigint':
                return '::integer';
            case 'decimal':
            case 'float':
                return '::float';
            case 'boolean':
                return '::boolean';
            case 'uuid':
            case 'guid':
                return '::uuid';
            case 'datetime':
            case 'datetimetz':
                return '::timestamp';
            case 'date':
                return '::date';
            case 'time':
                return '::time';
            case 'json':
                return '::json';
            case 'blob':
                return '::bytea';
            case 'inet':
                return '::inet';
            case 'macaddr':
                return '::macaddr';
            case 'string':
            case 'text':
            default:
                return '::text';
        }
    }

    private function initializeRareTypes(BatchInsert $insert) : void
    {
        $platform = $insert->dbConnection->getDoctrineConnection()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('macaddr', 'macaddr');
        $platform->registerDoctrineTypeMapping('inet', 'inet');
    }

    public static function registerRareTypes() : void
    {
        if (self::$rareTypesRegistered) {
            return;
        }

        if (class_exists(Type::class) && class_exists(Types\MacAddrType::class)) {
            Type::addType('text_array', Types\TextArrayType::class);
            Type::addType('int_array', Types\IntArrayType::class);
            Type::addType('tsvector', Types\TsvectorType::class);
            Type::addType('tsquery', Types\TsqueryType::class);
            Type::addType('xml', Types\XmlType::class);
            Type::addType('inet', Types\InetType::class);
            Type::addType('macaddr', Types\MacAddrType::class);

            self::$rareTypesRegistered = true;
        } else {
            throw new BatchInsertException('Missing required classes.');
        }
    }
}
