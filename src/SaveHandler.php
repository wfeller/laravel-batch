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

    /**
     * Strategy-pattern updaters: each database driver has its own updater
     * class that generates the appropriate SQL syntax. MySQL and SQLite
     * both use backtick quoting; PostgreSQL uses double-quote quoting
     * and requires explicit type casts.
     */
    private static array $updaters = [
        'pgsql' => Updater\PostgresUpdater::class,
        'mysql' => Updater\BacktickUpdater::class,
        'sqlite' => Updater\BacktickUpdater::class,
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

    /**
     * Rows in a single INSERT must share the same column structure.
     * We group items by their column signature (concatenated column names)
     * so each group can be inserted in one query. This handles models that
     * have different nullable columns present/absent.
     */
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

    /**
     * batchUpdate updates one column at a time across all models.
     * This allows the updater to generate a single CASE/WHEN SQL
     * statement per column instead of one query per model.
     */
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

    /**
     * Models are split into two groups: new models to INSERT and
     * existing dirty models to UPDATE. $finalModels tracks all models
     * that passed pre-insert events, for firing post-save events.
     */
    private function prepareBatches(array $modelsChunk) : array
    {
        [$createModels, $updateModels, $finalModels] = [[], [], []];

        foreach ($modelsChunk as $model) {
            $model = $model instanceof Model ? $model : $this->settings->model->newInstance()->forceFill($model);

            /**
             * isDirty() is checked before timestamps are set so we can
             * distinguish "model exists but nothing changed" from "model
             * exists with real changes" — only the latter goes to UPDATE.
             */
            $dirty = $model->isDirty();

            if (! $model->exists && $model->usesUniqueIds()) {
                $model->setUniqueIds();
            }

            if (! $this->firePreInsertModelEvents($model, $dirty)) {
                continue;
            }

            if ($this->settings->remembersBatchState) {
                $model->startBatchSave();
            }

            if ($this->settings->usesTimestamps && (! $model->exists || $dirty)) {
                if (! $model->exists) {
                    $model->setCreatedAt($this->settings->now);
                }

                $model->setUpdatedAt($this->settings->now);
            }

            if (! $model->exists) {
                $model->wasRecentlyCreated = true;
                $createModels[] = $model->getAttributes();
            } elseif ($dirty) {
                /**
                 * getDirty() returns only changed attributes, so we append
                 * the primary key so the updater knows which row to target.
                 */
                $updateModels[] = $model->getDirty() + [$this->settings->keyName => $model->getKey()];
            }

            $finalModels[] = $model;
        }

        return [$createModels, $updateModels, $finalModels];
    }

    /**
     * Pre-save events use halt mode (dispatcher::until): if any listener
     * returns false, the model is skipped entirely — not inserted or updated.
     * The saving event fires first; then either creating (new models) or
     * updating (existing dirty models) fires depending on the model state.
     */
    private function firePreInsertModelEvents(Model $model, bool $dirty) : bool
    {
        if ($this->settings->dispatchableEvents['saving']
            && false === $this->fireModelEvent($model, 'saving', true)
        ) {
            return false;
        }

        if ($model->exists
            && $dirty
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

    /**
     * Post-save events are non-halting (dispatcher::dispatch): all
     * listeners run and their return values are ignored. We check
     * isDirty() on updated models (not newly created ones) because
     * only updated models should fire the "updated" event — and
     * syncChanges() must be called before syncOriginal() so the
     * model's $changes property is populated correctly.
     */
    private function firePostInsertModelEvents(array $finalModels) : void
    {
        foreach ($finalModels as $model) {
            $model->exists = true;

            if ($model->wasRecentlyCreated && $this->settings->dispatchableEvents['created']) {
                $this->fireModelEvent($model, 'created', false);
            }

            if (! $model->wasRecentlyCreated && $model->isDirty()) {
                $model->syncChanges();

                if ($this->settings->dispatchableEvents['updated']) {
                    $this->fireModelEvent($model, 'updated', false);
                }
            }

            if ($this->settings->dispatchableEvents['saved']) {
                $this->fireModelEvent($model, 'saved', false);
            }

            $model->syncOriginal();

            if ($this->settings->remembersBatchState) {
                $model->finishBatchSave();
            }
        }
    }
}
