<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

class ConcurrencyException extends BaseException
{
    public function __construct(
        string $message = "The resource has been modified by another request. Please reload and try again.",
        array $context = []
    ) {
        parent::__construct(
            $message,
            'CONCURRENCY_CONFLICT',
            409,
            $context
        );
    }
}
