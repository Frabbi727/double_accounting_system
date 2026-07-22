<?php

declare(strict_types=1);

namespace Modules\Core\Traits;

use Illuminate\Support\Str;

trait HasUUID
{
    /**
     * Boot the UUID trait.
     */
    protected static function bootHasUUID(): void
    {
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Disable auto-incrementing since we use UUIDs.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Set the key type as string.
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
