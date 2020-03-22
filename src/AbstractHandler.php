<?php

namespace WF\Batch;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * @internal
 */
abstract class AbstractHandler implements ShouldQueue
{
    use Queueable;

    private bool $wasDispatched = false;

    protected Batch $batch;

    public function __construct(Batch $batch)
    {
        $this->batch = $batch;
    }

    public function dispatch() : void
    {
        $this->wasDispatched = true;

        dispatch($this);
    }

    public function now() : array
    {
        return $this->handle();
    }

    public function __destruct()
    {
        if (! $this->wasDispatched) {
            $this->handle();
        }
    }

    public function handle() : array
    {
        $this->wasDispatched = true;

        if ($this->batch->isEmpty()) {
            return [];
        }

        return $this->performAction();
    }

    abstract protected function performAction() : array;
}
