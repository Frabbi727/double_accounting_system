<?php

declare(strict_types=1);

namespace Modules\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Exceptions\ConcurrencyException;

trait HasOptimisticLocking
{
    /**
     * Boot the Optimistic Locking trait.
     */
    protected static function bootHasOptimisticLocking(): void
    {
        static::updating(function (Model $model) {
            $originalVersion = $model->getOriginal('version');

            if ($model->version !== $originalVersion) {
                throw new ConcurrencyException("Record has been modified by another process. Please reload and try again.");
            }

            $model->version = $originalVersion + 1;
        });
    }
}
