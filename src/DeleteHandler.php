<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;

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

            $this->performDelete($modelKeys = $this->getKeys($modelInstances));

            $ids = array_merge($ids, $modelKeys);

            $this->firePostDeleteEvents($modelInstances);
        }

        return $ids;
    }

    private function performDelete(array $keys) : void
    {
        if ($this->forceDelete) {
            $this->settings->model->newQuery()->whereKey($keys)->forceDelete();
        } else {
            // Soft deleting is taken care of in SoftDeletingScope
            $this->settings->model->newQuery()->whereKey($keys)->delete();
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

    private function prepareModels(array $models) : array
    {
        $this->removeNotExistingModels($models);

        if (! $this->hasAnyDeletionEvents()) {
            return $this->extractAndFilterModelKeys($models);
        }

        if (! $this->settings->dispatchableEvents['deleting']) {
            return $this->refreshModels($models);
        }

        $filteredModels = [];

        foreach ($this->refreshModels($models) as $model) {
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

        return $this->settings->model->newQuery()->whereKey($keys)->pluck($this->settings->keyName)->all();
    }

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
            return array_merge($models, $this->settings->model->newQuery()->findMany($missing)->all());
        }

        return $models;
    }

    private function firePostDeleteEvents(array $models) : void
    {
        if ($this->settings->dispatchableEvents['deleted']
            || ($this->forceDelete && $this->settings->dispatchableEvents['forceDeleted'])) {
            foreach ($models as $model) {
                if ($this->settings->dispatchableEvents['deleted']) {
                    $this->fireModelEvent($model, 'deleted', false);
                }

                if ($this->forceDelete && $this->settings->dispatchableEvents['forceDeleted']) {
                    $this->fireModelEvent($model, 'forceDeleted', false);
                }
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
