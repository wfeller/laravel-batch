<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DeleteHandler
 * @package WF\Batch
 * @internal
 */
final class DeleteHandler extends AbstractHandler
{
    /** @var Settings */
    private $settings;
    private $forceDelete = false;

    public function force(bool $forceDelete = true) : self
    {
        $this->forceDelete = $forceDelete;
        return $this;
    }

    public function handle() : array
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

        if (! $this->settings->events['deleting']) {
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
        if ($this->settings->events['deleted'] || ($this->forceDelete && $this->settings->events['forceDeleted'])) {
            foreach ($models as $model) {
                if ($this->settings->events['deleted']) {
                    $this->fireModelEvent($model, 'deleted', false);
                }

                if ($this->forceDelete && $this->settings->events['forceDeleted']) {
                    $this->fireModelEvent($model, 'forceDeleted', false);
                }
            }
        }
    }

    private function fireModelEvent(Model $model, string $event, bool $halt = true)
    {
        $method = $halt ? 'until' : 'dispatch';
        return $this->settings->dispatcher->{$method}("eloquent.{$event}: {$this->settings->class}", $model);
    }

    private function hasAnyDeletionEvents() : bool
    {
        return $this->settings->events['deleting']
            || $this->settings->events['deleting']
            || ($this->settings->events['forceDeleted'] && $this->forceDelete);
    }
}
