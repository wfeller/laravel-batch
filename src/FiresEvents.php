<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
trait FiresEvents
{
    /**
     * In halt mode (used for pre-save/delete events), the dispatcher
     * stops after the first listener returns a non-null value. If any
     * listener returns false, the entire operation is aborted for that
     * model. In non-halt mode (post-save/delete events), all listeners
     * run and return values are ignored.
     */
    private function fireModelEvent(Model $model, string $event, bool $halt = true)
    {
        $method = $halt ? 'until' : 'dispatch';

        $customEvent = $this->settings->customEvents[$event];

        /**
         * Custom events (from $dispatchesEvents) are dispatched first.
         * If a custom event listener returns false, we abort immediately
         * without also dispatching the standard Eloquent event.
         */
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
