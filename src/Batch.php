<?php

namespace WF\Batch;

use Illuminate\Database\Eloquent\Model;
use WF\Batch\Exceptions\BatchException;

/**
 * Class Batch
 * @package WF\Batch
 * @internal
 */
final class Batch
{
    private $models;
    private $class;
    private $batchSize = 500;

    public function __construct(iterable $models, string $class)
    {
        if (! is_a($class, Model::class, true)) {
            throw BatchException::notAnEloquentModel($class);
        }

        $this->models = is_array($models) ? $models : iterator_to_array($models, false);
        $this->class = $class;

        $this->verifyModels();
    }

    public static function of(string $class, iterable $models) : self
    {
        return new self($models, $class);
    }

    public function batchSize(int $batchSize) : self
    {
        if ($batchSize <= 0) {
            throw BatchException::batchSize($batchSize);
        }

        $this->batchSize = $batchSize;
        return $this;
    }

    public function getClass() : string
    {
        return $this->class;
    }

    public function getChunks() : array
    {
        return array_chunk($this->models, $this->batchSize);
    }

    public function save() : SaveHandler
    {
        return new SaveHandler($this);
    }

    public function delete() : DeleteHandler
    {
        return new DeleteHandler($this);
    }

    private function verifyModels() : void
    {
        foreach ($this->models as $model) {
            if ($model instanceof Model && ! $model instanceof $this->class) {
                throw BatchException::invalidClass($this->class, get_class($model));
            }
        }
    }
}
