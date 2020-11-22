<?php

namespace WF\Batch\Traits;

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
}
