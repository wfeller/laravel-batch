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

    /**
     * If neither now() nor dispatch() was called, the batch executes
     * automatically when the handler goes out of scope. This ensures
     * batches are never silently dropped. Exceptions during destruction
     * would cause a PHP fatal error, so we catch and defer the throw
     * to a shutdown function instead.
     */
    public function __destruct()
    {
        if (! $this->wasDispatched) {
            try {
                $this->handle();
            } catch (\Throwable $e) {
                register_shutdown_function(static fn () => throw $e);
            }
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
