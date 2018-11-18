<?php

namespace Wfeller\Batch;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Wfeller\Batch\Query\PostgresDriver;

class BatchInsert
{
    /** @var \Illuminate\Database\Connection */
    public $connection;
    public $settings;

    protected $items;
    protected $chunkSize;
    /** @var string|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder */
    protected $class;
    /** @var \Illuminate\Database\Eloquent\Model */
    protected $model;
    /** @var \Illuminate\Contracts\Events\Dispatcher|\Illuminate\Events\Dispatcher */
    protected $dispatcher;
    /** @var \Wfeller\Batch\Query\Driver */
    protected $query;

    public function __construct(array $items, int $chunkSize, string $class)
    {
        $this->items = $items;
        $this->chunkSize = $chunkSize;
        $this->class = $class;
        $this->model = new $class;
        $this->dispatcher = app(Dispatcher::class);
        $this->settings = [
            'connection' => $this->model->getConnectionName(),
            'table' => $this->model->getTable(),
            'incrementing' => $this->model->getIncrementing(),
            'keyName' => $this->model->getKeyName(),
            'keyType' => $this->model->getKeyType(),
            'usesTimestamps' => $this->model->usesTimestamps(),
        ];
        $this->connection = $this->model->getConnection();
        $this->setQuery();
    }

    protected function setQuery()
    {
        $driver = $this->connection->getDriverName();
        switch ($driver) :
            case 'pgsql':
                $this->query = new PostgresDriver($this);
                break;
            default:
                throw new \Exception("Database driver $driver does not have a query parser.");
        endswitch;
    }

    public function run() : array
    {
        $now = now();
        $ids = [];
        $eventTypes = $this->eventTypes();
        foreach (array_chunk($this->items, $this->chunkSize) as $modelsChunk) {
            $createModels = [];
            $updateModels = [];
            $finalModels = [];
            foreach ($modelsChunk as $model) {
                if (! $model instanceof Model) {
                    $model = (new $this->class)->forceFill($model);
                }
                /** @var \Illuminate\Database\Eloquent\Model $model */
                if ($eventTypes['saving'] && method_exists($model, 'fireModelEvent')) {
                    if ($this->fireModelEvent($model, 'saving', true) === false) {
                        continue;
                    }
                }
                if ($this->settings['usesTimestamps']) {
                    if (! $model->exists) {
                        $model->setCreatedAt($now);
                    }
                    $model->setUpdatedAt($now);
                }
                if ($model->exists) {
                    if ($eventTypes['updating']) {
                        if ($this->fireModelEvent($model, 'updating', true) === false) {
                            continue;
                        }
                    }
                    $updateModels[] = $model->getDirty() + [$model->getKeyName() => $model->getKey()];
                } else {
                    if ($eventTypes['creating']) {
                        if ($this->fireModelEvent($model, 'creating', true) === false) {
                            continue;
                        }
                    }
                    $model->wasRecentlyCreated = true;
                    $createModels[] = $model->getAttributes();
                }
                $finalModels[] = $model;
            }
            $ids = array_merge($ids, $this->batchInsert($createModels), $this->batchUpdate($updateModels));
            foreach ($finalModels as $model) {
                if ($model->wasRecentlyCreated && $eventTypes['created']) {
                    $this->fireModelEvent($model, 'created', false);
                } elseif ($eventTypes['updated']) {
                    $this->fireModelEvent($model, 'updated', false);
                }
                if ($eventTypes['saved']) {
                    $this->fireModelEvent($model, 'saved', false);
                }
            }
        }
        return $ids;
    }

    protected function batchInsert(array $items) : array
    {
        $ids = [];
        $id = null;
        $values = [];
        foreach ($items as $item) {
            if ($id = array_get($item, $this->settings['keyName'], false)) {
                $ids[] = $id;
            }
            $values[count($item)][] = $item;
        }
        foreach ($values as $insert) {
            $this->class::insert($insert);
        }
        return $ids;
    }

    protected function batchUpdate(array $items) : array
    {
        foreach ($this->getColumns() as $column) {
            $updated = array_filter($items, function ($arr) use ($column) {
                return array_key_exists($column, $arr);
            });
            if (count($updated) === 0) {
                continue;
            }
            $values = [];
            $stringValues = [];
            foreach ($updated as $item) {
                $values[] = $item[$this->settings['keyName']];
                $values[] = $item[$column];
                $stringValues[] = '(?, ?)';
            }
            $stringValues = implode(', ', $stringValues);
            $this->connection->update($this->query->rawUpdate($column, $stringValues), $values);
        }
        return array_pluck($items, $this->settings['keyName']);
    }

    protected function eventTypes() : array
    {
        $types = [];
        foreach (['saving', 'creating', 'updating', 'saved', 'created', 'updated'] as $type) {
            $types[$type] = count($this->dispatcher->getListeners("eloquent.{$type}: ".$this->class)) > 0;
        }
        return $types;
    }

    protected function fireModelEvent(Model $model, string $event, bool $halt = true)
    {
        $method = $halt ? 'until' : 'dispatch';
        return $this->dispatcher->{$method}("eloquent.{$event}: {$this->class}", $model);
    }

    protected function getColumns() : array
    {
        if (isset($this->settings['columns'])) {
            return $this->settings['columns'];
        }
        return $this->settings['columns'] = array_filter(
            $this->connection->getSchemaBuilder()->getColumnListing($this->settings['table']),
            function ($value) {
                return $value !== $this->settings['keyName'];
            }
        );
    }
}
