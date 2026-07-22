<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseService
{
    /**
     * Execute a callback inside a database transaction.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws Throwable
     */
    protected function dbTransaction(callable $callback)
    {
        return DB::transaction($callback);
    }

    /**
     * Log a message with contextual module information.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info(sprintf('[%s] %s', class_basename($this), $message), $context);
    }

    /**
     * Log an error message with contextual module information and stack trace.
     */
    protected function logError(string $message, Throwable $exception, array $context = []): void
    {
        Log::error(sprintf('[%s] %s: %s', class_basename($this), $message, $exception->getMessage()), array_merge([
            'exception' => $exception,
            'trace' => $exception->getTraceAsString(),
        ], $context));
    }
}
