<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
trait FiresEvents
{
    private function fireModelEvent(Model $model, string $event, bool $halt = true)
    {
        $method = $halt ? 'until' : 'dispatch';

        $customEvent = $this->settings->customEvents[$event];

        $result = $customEvent
            ? $this->filterModelEventResults($this->settings->dispatcher->{$method}(new $customEvent($model)))
            : null;

        if (false === $result) {
            return false;
        }

        if (! empty($result)) {
            return $result;
        }

        if (! $this->settings->events[$event]) {
            return null;
        }

        return $this->settings->dispatcher->{$method}("eloquent.{$event}: {$this->settings->class}", $model);
    }

    private function filterModelEventResults($result)
    {
        if (is_array($result)) {
            $result = array_filter($result, static fn ($response) : bool => null !== $response);
        }

        return $result;
    }
}
