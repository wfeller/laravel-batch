<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use WF\Batch\Exceptions\BatchException;

/**
 * @internal
 */
final class SaveHandler extends AbstractHandler
{
    use FiresEvents;

    private Settings $settings;
    private Updater\Updater $updater;

    private static array $updaters = [
        'pgsql' => Updater\PostgresUpdater::class,
        'mysql' => Updater\GenericUpdater::class,
        'sqlite' => Updater\GenericUpdater::class,
    ];

    public static function registerUpdater(string $driver, string $class) : void
    {
        if (! is_a($class, Updater\Updater::class, true)) {
            throw BatchException::notAnUpdater($class);
        }

        self::$updaters[$driver] = $class;
    }

    protected function performAction() : array
    {
        $this->settings = new Settings($this->batch);
        $this->initializeUpdater();

        $ids = [];

        foreach ($this->batch->getChunks() as $chunk) {
            [$createModels, $updateModels, $finalModels] = $this->prepareBatches($chunk);

            $ids = array_merge($ids, $this->batchInsert($createModels), $this->batchUpdate($updateModels));

            $this->firePostInsertModelEvents($finalModels);
        }

        return $ids;
    }

    private function initializeUpdater() : void
    {
        $driver = $this->settings->dbConnection->getDriverName();

        if (! isset(self::$updaters[$driver])) {
            throw BatchException::noRegisteredUpdater($driver);
        }

        $this->updater = app(self::$updaters[$driver]);
    }

    private function batchInsert(array $items) : array
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
            $this->settings->model->newQuery()->insert($insert);
        }

        return $ids;
    }

    private function batchUpdate(array $models) : array
    {
        foreach ($this->settings->getColumns() as $column) {
            $updated = array_filter($models, static fn ($model) : bool => array_key_exists($column, $model));

            if (empty($updated)) {
                continue;
            }

            $this->updater->performUpdate($this->settings, $column, ...$this->pullUpdateValues($updated, $column));
        }

        return Arr::pluck($models, $this->settings->keyName);
    }

    private function pullUpdateValues(array $updated, string $column) : array
    {
        $ids = [];
        $values = [];

        foreach ($updated as $item) {
            $ids[] = $item[$this->settings->keyName];
            $values[] = $item[$column];
        }

        return [$values, $ids];
    }

    private function prepareBatches(array $modelsChunk) : array
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

    private function prepareModel($model) : Model
    {
        if (! $model instanceof Model) {
            $model = $this->settings->model->newInstance()->forceFill($model);
        }

        if ($this->settings->usesTimestamps) {
            if (! $model->exists) {
                $model->setCreatedAt($this->settings->now);
            }

            $model->setUpdatedAt($this->settings->now);
        }

        return $model;
    }

    private function firePreInsertModelEvents(Model $model) : bool
    {
        if ($this->settings->dispatchableEvents['saving']
            && false === $this->fireModelEvent($model, 'saving', true)
        ) {
            return false;
        }

        if ($model->exists
            && $this->settings->dispatchableEvents['updating']
            && false === $this->fireModelEvent($model, 'updating', true)
        ) {
            return false;
        } elseif (! $model->exists
            && $this->settings->dispatchableEvents['creating']
            && false === $this->fireModelEvent($model, 'creating', true)
        ) {
            return false;
        }

        return true;
    }

    private function firePostInsertModelEvents(array $finalModels) : void
    {
        foreach ($finalModels as $model) {
            $model->exists = true;

            if ($model->wasRecentlyCreated && $this->settings->dispatchableEvents['created']) {
                $this->fireModelEvent($model, 'created', false);
            } elseif ($this->settings->dispatchableEvents['updated']) {
                $this->fireModelEvent($model, 'updated', false);
            }

            if ($this->settings->dispatchableEvents['saved']) {
                $this->fireModelEvent($model, 'saved', false);
            }

            $model->syncOriginal();
        }
    }
}
