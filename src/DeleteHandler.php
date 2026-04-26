<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WF\Batch\Events\BatchDeleted;
use WF\Batch\Events\BatchDeleting;

/**
 * @internal
 */
final class DeleteHandler extends AbstractHandler
{
    use FiresEvents;

    private Settings $settings;
    private bool $forceDelete = false;

    public function force(bool $forceDelete = true) : self
    {
        $this->forceDelete = $forceDelete;

        return $this;
    }

    protected function performAction() : array
    {
        $this->settings = new Settings($this->batch);

        $ids = [];

        foreach ($this->batch->getChunks() as $chunk) {
            $modelInstances = $this->prepareModels($chunk);

            $modelKeys = $this->getKeys($modelInstances);

            event(new BatchDeleting($this->settings->model, $modelKeys, $this->forceDelete));

            $this->performDelete($modelKeys);

            $ids = array_merge($ids, $modelKeys);

            $this->firePostDeleteEvents($modelInstances);

            event(new BatchDeleted($this->settings->model, $modelKeys, $this->forceDelete));
        }

        return $ids;
    }

    /**
     * We use newQueryWithoutScopes() so that global scopes (e.g. tenant
     * scope) don't prevent deleting the target rows. For soft-deletable
     * models, we explicitly set deleted_at via UPDATE instead of relying
     * on SoftDeletingScope (which was stripped), and add a whereNull
     * clause so already-deleted rows are not re-timestamped.
     */
    private function performDelete(array $keys) : void
    {
        $query = $this->settings->model->newQueryWithoutScopes()->whereKey($keys);

        if ($this->forceDelete) {
            $query->forceDelete();
        } elseif (isset(class_uses_recursive($this->settings->model)[SoftDeletes::class])) {
            $query
                ->whereNull($this->settings->model->getDeletedAtColumn())
                ->update([$this->settings->model->getDeletedAtColumn() => $this->settings->now]);
        } else {
            $query->delete();
        }
    }

    private function getKeys(array $models) : array
    {
        if (! $this->hasAnyDeletionEvents()) {
            return $models;
        }

        $keys = [];

        foreach ($models as $model) {
            $keys[] = $model->getKey();
        }

        return $keys;
    }

    /**
     * When no deletion events are registered, we can skip fetching model
     * instances from the database entirely — we only need their keys.
     * This is a performance optimization: extractAndFilterModelKeys
     * returns raw integer/string IDs instead of Model instances.
     */
    private function prepareModels(array $models) : array
    {
        $this->removeNotExistingModels($models);

        if (! $this->hasAnyDeletionEvents()) {
            return $this->extractAndFilterModelKeys($models);
        }

        $models = $this->refreshModels($models);

        if ($this->settings->remembersBatchState) {
            foreach ($models as $model) {
                $model->startBatchDelete();
            }
        }

        if (! $this->settings->dispatchableEvents['deleting']) {
            return $models;
        }

        $filteredModels = [];

        foreach ($models as $model) {
            if (false !== $this->fireModelEvent($model, 'deleting', true)) {
                $filteredModels[] = $model;
            }
        }

        return $filteredModels;
    }

    private function removeNotExistingModels(array &$models) : void
    {
        foreach ($models as $key => $model) {
            if ($model instanceof Model && ! $model->exists) {
                unset($models[$key]);
            }
        }
    }

    private function extractAndFilterModelKeys(array $models) : array
    {
        $keys = [];

        foreach ($models as $model) {
            if ($model instanceof Model) {
                $keys[] = $model->getKey();
            } else {
                $keys[] = $model;
            }
        }

        return $this->settings->model->newQueryWithoutScopes()->whereKey($keys)->pluck($this->settings->keyName)->all();
    }

    /**
     * When deletion events exist, we need actual Model instances to fire
     * events on. Raw IDs are refreshed from the database; existing Model
     * instances are kept as-is. When no events exist, we return only keys
     * (from extractAndFilterModelKeys) and skip the DB refresh entirely.
     */
    private function refreshModels(array $models) : array
    {
        $missing = [];

        foreach ($models as $key => $model) {
            if (! $model instanceof Model) {
                $missing[] = $model;
                unset($models[$key]);
            }
        }

        if (! empty($missing)) {
            return array_merge($models, $this->settings->model->newQueryWithoutScopes()->findMany($missing)->all());
        }

        return $models;
    }

    private function firePostDeleteEvents(array $models) : void
    {
        foreach ($models as $model) {
            if ($this->settings->dispatchableEvents['deleted']) {
                $this->fireModelEvent($model, 'deleted', false);
            }

            if ($this->forceDelete && $this->settings->dispatchableEvents['forceDeleted']) {
                $this->fireModelEvent($model, 'forceDeleted', false);
            }

            if ($this->settings->remembersBatchState && $model instanceof Model) {
                $model->finishBatchDelete();
            }
        }
    }

    private function hasAnyDeletionEvents() : bool
    {
        return $this->settings->dispatchableEvents['deleted']
            || $this->settings->dispatchableEvents['deleting']
            || ($this->settings->dispatchableEvents['forceDeleted'] && $this->forceDelete);
    }
}
