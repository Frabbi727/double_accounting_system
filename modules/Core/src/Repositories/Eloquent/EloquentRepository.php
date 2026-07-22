<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Eloquent;

use Modules\Core\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class EloquentRepository implements BaseRepositoryInterface
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * EloquentRepository constructor.
     */
    public function __construct()
    {
        $this->model = $this->resolveModel();
    }

    /**
     * Resolve the Model class for the repository.
     */
    abstract protected function resolveModel(): Model;

    /**
     * Find a record by its primary key ID.
     */
    public function find(string $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * Find a record by its primary key ID, or throw a ModelNotFoundException.
     */
    public function findOrFail(string $id): Model
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Get all records.
     */
    public function all(): Collection
    {
        return $this->model->all();
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing record.
     */
    public function update(string $id, array $data): bool
    {
        $record = $this->findOrFail($id);
        return $record->update($data);
    }

    /**
     * Delete a record by ID.
     */
    public function delete(string $id): bool
    {
        $record = $this->find($id);
        if (!$record) {
            return false;
        }
        return (bool) $record->delete();
    }
}
