<?php

declare(strict_types=1);

namespace Modules\Core\DTOs;

abstract readonly class BaseDTO
{
    /**
     * Create a DTO instance from an associative array.
     */
    public static function fromArray(array $data): static
    {
        return new static(...$data);
    }

    /**
     * Convert the DTO properties to an associative array.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
