<?php

declare(strict_types=1);

namespace Modules\Core\DTOs;

abstract readonly class BaseDTO
{
    /**
     * Create a DTO instance from an associative array.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        // @phpstan-ignore-next-line new.static
        return new static(...$data);
    }

    /**
     * Convert the DTO properties to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
