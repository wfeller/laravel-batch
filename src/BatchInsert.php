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

    public $dbConnection;
    public $settings;

    public $items;
    public $chunkSize;
    /** @var \Illuminate\Database\Eloquent\Model */
    public $class;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $model;
    /** @var \Illuminate\Contracts\Events\Dispatcher */
    protected $dispatcher;
    /** @var \WF\Batch\Query\Driver */
    protected $query;

    protected $eventTypes;
    protected $now;

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
        $this->dbConnection = $this->model->getConnection();
        $this->setQuery();
        $this->now = Carbon::now();
        $this->eventTypes = $this->eventTypes();
    }

    public static function registerDriver(string $driver, string $class) : void
    {
        if (! is_a($class, Driver::class, true)) {
            throw new BatchInsertException("'$class' does not extend '".Driver::class."'.");
        }
        static::$drivers[$driver] = $class;
    }

    public function handle() : array
    {
        $ids = [];
        foreach (array_chunk($this->items, $this->chunkSize) as $modelsChunk) {
            list($createModels, $updateModels, $finalModels) = $this->prepareBatches($modelsChunk);

            $ids = array_merge($ids, $this->batchInsert($createModels), $this->batchUpdate($updateModels));

            $this->firePostInsertModelEvents($finalModels);
        }
        return $ids;
    }

    protected function batchInsert(array $items) : array
    {
        $ids = [];
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

    protected function batchUpdate(array $models) : array
    {
        foreach ($this->settings->columns as $column) {
            $updated = array_filter($models, function ($model) use ($column) {
                return array_key_exists($column, $model);
            });
            if (empty($updated)) {
                continue;
            }
            $this->query->performUpdate($column, ...$this->pullUpdateValues($updated, $column));
        }
        return Arr::pluck($models, $this->settings->keyName);
    }

    protected function pullUpdateValues(array $updated, string $column) : array
    {
        $ids = [];
        $values = [];
        foreach ($updated as $item) {
            $ids[] = $item[$this->settings->keyName];
            $values[] = $item[$column];
        }
        return [$values, $ids];
    }

    protected function prepareBatches(array $modelsChunk) : array
    {
        list($createModels, $updateModels, $finalModels) = [[], [], []];
        foreach ($modelsChunk as $model) {
            $model = $this->prepareModel($model);

            if (! $this->firePreInsertModelEvents($model)) {
                continue;
            }

            if ($model->exists) {
                $updateModels[] = $model->getDirty() + [$this->settings->keyName => $model->getKey()];
            } else {
                $model->wasRecentlyCreated = true;
                $createModels[] = $model->getAttributes();
            }

            $finalModels[] = $model;
        }
        return [$createModels, $updateModels, $finalModels];
    }

    protected function prepareModel($model) : Model
    {
        if (! $model instanceof Model) {
            $model = (new $this->class)->forceFill($model);
        }
        if ($this->settings->usesTimestamps) {
            if (! $model->exists) {
                $model->setCreatedAt($this->now);
            }
            $model->setUpdatedAt($this->now);
        }
        return $model;
    }

    protected function firePreInsertModelEvents(Model $model) : bool
    {
        if ($this->eventTypes['saving']
            && $this->fireModelEvent($model, 'saving', true) === false
        ) {
            return false;
        }

        if ($model->exists
            && $this->eventTypes['updating']
            && $this->fireModelEvent($model, 'updating', true) === false
        ) {
            return false;
        } elseif ($this->eventTypes['creating']
            && $this->fireModelEvent($model, 'creating', true) === false
        ) {
            return false;
        }

        return true;
    }

    protected function firePostInsertModelEvents(array $finalModels) : void
    {
        foreach ($finalModels as $model) {
            if ($model->wasRecentlyCreated && $this->eventTypes['created']) {
                $this->fireModelEvent($model, 'created', false);
            } elseif ($this->eventTypes['updated']) {
                $this->fireModelEvent($model, 'updated', false);
            }
            if ($this->eventTypes['saved']) {
                $this->fireModelEvent($model, 'saved', false);
            }
        }
    }

    protected function eventTypes() : array
    {
        $types = [];
        foreach (['saving', 'creating', 'updating', 'saved', 'created', 'updated'] as $type) {
            $types[$type] = count($this->dispatcher->getListeners("eloquent.{$type}: {$this->class}")) > 0;
        }
        return $types;
    }

    protected function fireModelEvent(Model $model, string $event, bool $halt = true)
    {
        $method = $halt ? 'until' : 'dispatch';
        return $this->dispatcher->{$method}("eloquent.{$event}: {$this->class}", $model);
    }

    protected function setQuery() : void
    {
        $driver = $this->dbConnection->getDriverName();

        if (isset(static::$drivers[$driver])) {
            $class = static::$drivers[$driver];
            $this->query = new $class($this);
            return;
        }

        throw new BatchInsertException("Database driver '$driver' does not have a query parser.");
    }
}
