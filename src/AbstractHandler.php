<?php

namespace WF\Batch;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Class AbstractHandler
 * @package WF\Batch
 * @internal
 */
abstract class AbstractHandler implements ShouldQueue
{
    use Queueable;

    protected $batch;

    public function __construct(Batch $batch)
    {
        $this->batch = $batch;
    }

    public function dispatch() : void
    {
        dispatch($this);
    }

    public function now() : array
    {
        return $this->handle();
    }

    abstract public function handle() : array;
}
