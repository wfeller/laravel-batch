<?php

namespace WF\Batch;

/**
 * @internal
 */
final class Settings
{
    private static $columns = [];

    public $class;
    /** @var \Illuminate\Database\Eloquent\Model */
    public $model;

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
            $this->events[$type] = $this->dispatcher->hasListeners("eloquent.{$type}: {$this->class}");
        }
    }

    public function getColumns() : array
    {
        if (! isset(self::$columns[$this->class])) {
            self::$columns[$this->class] = array_filter(
                $this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->table),
                function ($value) : bool {
                    return $value !== $this->keyName;
                }
            );
        }

        return self::$columns[$this->class];
    }
}
