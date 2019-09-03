<?php

namespace WF\Batch\Updater;

use Doctrine\DBAL\PostgresTypes as Types;
use Doctrine\DBAL\Types\Type;
use WF\Batch\Exceptions\BatchException;
use WF\Batch\Helpers\Alternate;
use WF\Batch\Settings;

final class PostgresUpdater implements Updater
{
    private $keyCast;
    private static $castTypes = [];
    private static $rareTypesRegistered = false;

    public function performUpdate(Settings $settings, string $column, array $values, array $ids) : void
    {
        $this->keyCast = in_array($settings->keyType, ['int', 'integer']) ? '::integer' : '::text';

        if (self::$rareTypesRegistered) {
            $this->initializeRareTypes($settings);
        }

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

    private function initializeRareTypes(Settings $settings) : void
    {
        $platform = $settings->dbConnection->getDoctrineConnection()->getDatabasePlatform();
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
            throw BatchException::missingPostgresTypesDependency();
        }
    }
}
