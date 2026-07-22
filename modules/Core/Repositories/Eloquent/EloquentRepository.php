<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Eloquent;

use Modules\Core\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

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
        /** @var Model|null $record */
        $record = $this->model->newQuery()->find($id);
        return $record;
    }

    /**
     * Find a record by its primary key ID, or throw a ModelNotFoundException.
     */
    public function findOrFail(string $id): Model
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    /**
     * Get all records.
     *
     * @return Collection<int, Model>
     */
    public function all(): Collection
    {
        /** @var Collection<int, Model> $collection */
        $collection = $this->model->newQuery()->get();
        return $collection;
    }

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Update an existing record.
     *
     * @param array<string, mixed> $data
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
