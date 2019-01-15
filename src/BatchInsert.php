<?php

namespace WF\Batch;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use WF\Batch\Exceptions\BatchInsertException;
use WF\Batch\Query\Driver;
use WF\Batch\Query\GenericDriver;
use WF\Batch\Query\PostgresDriver;

class BatchInsert implements ShouldQueue
{
    use Queueable;

    public $connection;
    public $settings;

    public $items;
    public $chunkSize;
    /** @var \Illuminate\Database\Eloquent\Model */
    public $class;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $model;
    protected $dispatcher;
    /** @var \WF\Batch\Query\Driver */
    protected $query;

    protected static $drivers = [
        'pgsql' => PostgresDriver::class,
        'mysql' => GenericDriver::class,
        'sqlite' => GenericDriver::class,
    ];

    public function __construct(array $items, int $chunkSize, string $class)
    {
        $this->items = $items;
        $this->chunkSize = $chunkSize;
        $this->class = $class;
        $this->model = new $class;
        $this->settings = new Settings($this->model);
        $this->dispatcher = app(Dispatcher::class);
        $this->connection = $this->model->getConnection();
        $this->setQuery();
    }

    public static function registerDriver(string $driver, string $class) : void
    {
        if (! is_a($class, Driver::class, true)) {
            throw new BatchInsertException("'$class' does not extend '".Driver::class."'.");
        }
        static::$drivers[$driver] = $class;
    }

    protected function setQuery() : void
    {
        $driver = $this->connection->getDriverName();

        if (isset(static::$drivers[$driver])) {
            $class = static::$drivers[$driver];
            $this->query = new $class($this);
            return;
        }

        throw new BatchInsertException("Database driver '$driver'' does not have a query parser.");
    }

    public function handle() : array
    {
        $now = Carbon::now();
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
                if ($this->settings->usesTimestamps) {
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
            if ($id = Arr::get($item, $this->settings->keyName, false)) {
                $ids[] = $id;
            }
            $values[count($item)][] = $item;
        }
        foreach ($values as $insert) {
            $this->class::query()->insert($insert);
        }
        return $ids;
    }

    protected function batchUpdate(array $items) : array
    {
        foreach ($this->settings->columns as $column) {
            $updated = array_filter($items, function ($arr) use ($column) {
                return array_key_exists($column, $arr);
            });
            if (count($updated) === 0) {
                continue;
            }
            $ids = [];
            $values = [];
            foreach ($updated as $item) {
                $ids[] = $item[$this->settings->keyName];
                $values[] = $item[$column];
            }
            $this->query->performUpdate($column, $values, $ids);
        }
        return Arr::pluck($items, $this->settings->keyName);
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
}
