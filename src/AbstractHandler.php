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

    private $wasDispatched = false;

    protected $batch;

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

        return $this->performAction();
    }

    abstract protected function performAction() : array;
}
