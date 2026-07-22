<?php

declare(strict_types=1);

namespace Modules\Core\ValueObjects;

abstract readonly class ValueObject
{
    /**
     * Determine if this value object is equal to another value object of the same type.
     */
    public function equals(self $other): bool
    {
        if (get_class($this) !== get_class($other)) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    /**
     * Get the array representation of the value object.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
