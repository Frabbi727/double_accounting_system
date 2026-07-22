<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Exception;
use Throwable;

abstract class BaseException extends Exception
{
    protected string $errorCode;
    protected int $statusCode;
    
    /**
     * @var array<string, mixed>
     */
    protected array $context;

    /**
     * BaseException constructor.
     *
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = "",
        string $errorCode = "SYSTEM_ERROR",
        int $statusCode = 500,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the enterprise-wide error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the validation/contextual details of the error.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
