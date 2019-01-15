<?php

namespace WF\Batch\Query;

use Doctrine\DBAL\PostgresTypes as Types;
use Doctrine\DBAL\Types\Type;
use WF\Batch\BatchInsert;
use WF\Batch\Exceptions\BatchInsertException;
use WF\Batch\Helpers\Alternate;

class PostgresDriver extends Driver
{
    protected static $castTypes = [];
    protected $keyCast;

    protected static $rareTypesRegistered = false;

    public function __construct(BatchInsert $insert)
    {
        parent::__construct($insert);
        $this->keyCast = in_array($this->insert->settings->keyType, ['int', 'integer']) ? '::integer' : '::text';
        if (static::$rareTypesRegistered) {
            $this->initializeRareType();
        }
    }

    protected function initializeRareType() : void
    {
        $platform = $this->insert->connection->getDoctrineConnection()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('macaddr', 'macaddr');
        $platform->registerDoctrineTypeMapping('inet', 'inet');
    }

    public static function registerRareTypes() : void
    {
        if (static::$rareTypesRegistered) {
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

            static::$rareTypesRegistered = true;
        } else {
            throw new BatchInsertException('Missing required classes.');
        }
    }

    protected function sql(string $column, array $values, array $ids) : string
    {
        $stringValues = implode(',', array_fill(0, count($values), '(?, ?)'));
        return "UPDATE \"{$this->insert->settings->table}\" AS t
                SET \"{$column}\" = (help_c.column_copy){$this->castTypeForColumn($column)}
                FROM (VALUES {$stringValues}) AS help_c(column_id, column_copy)
                WHERE (help_c.column_id){$this->keyCast} = t.\"{$this->insert->settings->keyName}\"{$this->keyCast}";
    }

    protected function bindings(string $column, array $values, array $ids) : array
    {
        return Alternate::arrays($ids, $values);
    }

    protected function castTypeForColumn(string $column) : string
    {
        if (! isset(static::$castTypes[$this->insert->settings->table][$column])) {
            static::$castTypes[$this->insert->settings->table][$column] = $this->castType(
                $this->insert->connection
                             ->getSchemaBuilder()
                             ->getColumnType($this->insert->settings->table, $column)
            );
        }
        return static::$castTypes[$this->insert->settings->table][$column];
    }

    protected function castType(string $type) : string
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
}
