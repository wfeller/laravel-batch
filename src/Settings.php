<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Settings
 * @package WF\Batch
 * @internal
 */
final class Settings
{
    public $class;
    /** @var Model */
    public $model;
    private $columns = null;

    public $table;
    public $keyName;
    public $keyType;
    public $usesTimestamps;

    public $now;
    public $dbConnection;
    public $dispatcher;

    public $events = [];

    public function __construct(Batch $batch)
    {
        $class = $batch->getClass();
        $this->class = $class;
        $this->model = new $class;
        $this->table = $this->model->getTable();
        $this->keyName = $this->model->getKeyName();
        $this->keyType = $this->model->getKeyType();
        $this->usesTimestamps = $this->model->usesTimestamps();
        $this->now = $this->model->freshTimestamp();
        $this->dbConnection = $this->model->getConnection();
        $this->dispatcher = $this->model->getEventDispatcher();

        foreach ($this->model->getObservableEvents() as $type) {
            $this->events[$type] = count($this->dispatcher->getListeners("eloquent.{$type}: {$this->class}")) > 0;
        }
    }

    public function getColumns() : array
    {
        if (null === $this->columns) {
            $this->columns = array_filter(
                $this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->table),
                function ($value) {
                    return $value !== $this->keyName;
                }
            );
        }

        return $this->columns;
    }
}
