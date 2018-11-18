<?php

namespace Wfeller\Batch\Query;

use Wfeller\Batch\BatchInsert;

class PostgresDriver extends Driver
{
    protected $castTypes = [];
    protected $keyCast;

    public function __construct(BatchInsert $insert)
    {
        parent::__construct($insert);
        $usesTextId = ! in_array($this->insert->settings['keyType'], ['int', 'integer']);
        $this->keyCast = $usesTextId ? '::TEXT' : '::INTEGER';
    }

    public function rawUpdate(string $column, string $stringValues) : string
    {
        return "UPDATE {$this->insert->settings['table']} AS t
                SET {$column} = (help_c.column_copy){$this->castTypeForColumn($column)}
                FROM (VALUES {$stringValues}) AS help_c(column_id, column_copy)
                WHERE (help_c.column_id){$this->keyCast} = t.{$this->insert->settings['keyName']}{$this->keyCast}";
    }

    protected function castTypeForColumn(string $column) : string
    {
        if (isset($this->castTypes[$column])) {
            return $this->castTypes[$column];
        }
        return $this->castTypes[$column] = $this->castType(
            $this->insert->connection->getSchemaBuilder()->getColumnType($this->insert->settings['table'], $column)
        );
    }

    protected function castType(string $type) : string
    {
        switch ($type) :
            case 'smallint':
            case 'integer':
                return '::integer';
            case 'decimal':
                return '::float';
            case 'boolean':
                return '::boolean';
            case 'uuid':
            case 'guid':
                return '::uuid';
            case 'datetime':
                return '::timestamp';
            case 'date':
                return '::date';
            case 'time':
                return '::time';//watch out, this does not seem to keep timezone
            case 'json':
                return '::json';
            case 'string':
            case 'text':
            default:
                return '::text';
        endswitch;
    }
}
