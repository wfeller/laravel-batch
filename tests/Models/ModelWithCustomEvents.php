<?php

namespace WF\Batch\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class ModelWithCustomEvents extends Model
{
    protected $table = 'test_models';

    protected $dispatchesEvents = [
        'saving' => SavingEvent::class,
        'saved' => SavedEvent::class,
        'creating' => CreatingEvent::class,
        'created' => CreatedEvent::class,
        'updating' => UpdatingEvent::class,
        'updated' => UpdatedEvent::class,
        'deleting' => DeletingEvent::class,
        'deleted' => DeletedEvent::class,
        'forceDeleted' => ForceDeletedEvent::class,
    ];

    public function getCustomEvents()
    {
        return $this->dispatchesEvents;
    }
}

class SavingEvent{public $model; public function __construct($m){$this->model=$m;}}
class SavedEvent{public $model; public function __construct($m){$this->model=$m;}}
class CreatingEvent{public $model; public function __construct($m){$this->model=$m;}}
class CreatedEvent{public $model; public function __construct($m){$this->model=$m;}}
class UpdatingEvent{public $model; public function __construct($m){$this->model=$m;}}
class UpdatedEvent{public $model; public function __construct($m){$this->model=$m;}}
class DeletingEvent{public $model; public function __construct($m){$this->model=$m;}}
class DeletedEvent{public $model; public function __construct($m){$this->model=$m;}}
class ForceDeletedEvent{public $model; public function __construct($m){$this->model=$m;}}
