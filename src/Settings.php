<?php

namespace WF\Batch;

use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
final class Settings
{
    private static $columns = [];

    public string $class;
    public Model $model;

    public string $table;
    public string $keyName;
    public string $keyType;
    public bool $usesTimestamps;

    public DateTimeInterface $now;
    public Connection $dbConnection;
    public Dispatcher $dispatcher;

    public array $events = [];

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
