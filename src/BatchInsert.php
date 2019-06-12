<?php

namespace WF\Batch;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use WF\Batch\Exceptions\BatchInsertException;

class BatchInsert implements ShouldQueue
{
    use Queueable;

    /** @var \Illuminate\Database\Connection */
    public $dbConnection;
    /** @var \WF\Batch\Settings */
    public $settings;

    /** @var array|iterable */
    public $items;
    /** @var integer */
    public $chunkSize;

    /** @var \Illuminate\Database\Eloquent\Model|string */
    public $class;
    /** @var \Illuminate\Database\Eloquent\Model */
    protected $model;

    /** @var \Illuminate\Events\Dispatcher */
    protected $dispatcher;
    /** @var \WF\Batch\Updater\Updater */
    protected $updater;

    /** @var array */
    protected $eventTypes;
    /** @var \DateTimeInterface */
    protected $now;

    protected static $updaters = [
        'pgsql' => Updater\PostgresUpdater::class,
        'mysql' => Updater\GenericUpdater::class,
        'sqlite' => Updater\GenericUpdater::class,
    ];

    public function __construct(iterable $items, int $chunkSize, string $class)
    {
        $this->items = is_array($items) ? $items : iterator_to_array($items, false);
        $this->chunkSize = $chunkSize;
        $this->class = $class;
    }

    public static function registerDriver(string $driver, string $class) : void
    {
        if (! is_a($class, Updater\Updater::class, true)) {
            throw new BatchInsertException("'$class' is not an '".Updater\Updater::class."'.");
        }

        static::$updaters[$driver] = $class;
    }

    public function handle() : array
    {
        $this->setup();

        $ids = [];

        foreach (array_chunk($this->items, $this->chunkSize) as $modelsChunk) {
            [$createModels, $updateModels, $finalModels] = $this->prepareBatches($modelsChunk);

            $ids = array_merge($ids, $this->batchInsert($createModels), $this->batchUpdate($updateModels));

            $this->firePostInsertModelEvents($finalModels);
        }

        return $ids;
    }

    protected function setup() : void
    {
        $this->model = new $this->class;
        $this->settings = new Settings($this->model);
        $this->dispatcher = $this->model->getEventDispatcher();
        $this->dbConnection = $this->model->getConnection();
        $this->now = $this->model->freshTimestamp();
        $this->eventTypes = $this->eventTypes();
        $this->initializeUpdater();
    }

    protected function initializeUpdater() : void
    {
        $driver = $this->dbConnection->getDriverName();

        if (! isset(static::$updaters[$driver])) {
            throw new BatchInsertException("Database driver '$driver' does not have an updater.");
        }

        $this->updater = app(static::$updaters[$driver]);
    }

    protected function batchInsert(array $items) : array
    {
        $ids = [];
        $values = [];
        foreach ($items as $item) {
            if ($id = $item[$this->settings->keyName] ?? false) {
                $ids[] = $id;
            }

            $values[implode('', array_keys($item))][] = $item;
        }
        foreach ($values as $insert) {
            $this->model->newQuery()->insert($insert);
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
            $this->updater->performUpdate($this, $column, ...$this->pullUpdateValues($updated, $column));
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
        [$createModels, $updateModels, $finalModels] = [[], [], []];
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
            $model = $this->model->newInstance()->forceFill($model);
        } elseif (! $model instanceof $this->class) {
            throw new BatchInsertException("Unexpected class '".get_class($model)."'.");
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
            $model->exists = true;

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
}
