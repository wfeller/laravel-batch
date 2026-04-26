<?php

namespace WF\Batch\Traits;

/**
 * Add this trait to models that need to know when they are being batched.
 * $batchSaving / $batchDeleting are set to true at the start of the batch
 * operation and reset to false when the batch completes for that model.
 */
trait RemembersBatchState
{
    public bool $batchDeleting = false;

    public bool $batchSaving = false;

    public function startBatchDelete() : void
    {
        $this->batchDeleting = true;
    }

    public function startBatchSave() : void
    {
        $this->batchSaving = true;
    }

    public function finishBatchDelete() : void
    {
        $this->batchDeleting = false;
    }

    public function finishBatchSave() : void
    {
        $this->batchSaving = false;
    }
}
