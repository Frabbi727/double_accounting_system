<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

interface BaseRepositoryInterface
{
    /**
     * Find a record by its primary key ID.
     */
    public function find(string $id): ?Model;

    /**
     * Find a record by its primary key ID, or throw a ModelNotFoundException.
     */
    public function findOrFail(string $id): Model;

    /**
     * Get all records.
     */
    public function all(): Collection;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update an existing record.
     */
    public function update(string $id, array $data): bool;

    /**
     * Delete a record by ID.
     */
    public function delete(string $id): bool;
}
