<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks daily transaction routes (sale, purchase, expense, payment) until the
 * opening balances have been locked — requirements FR-18 / §6.3: stock must be
 * in before anything can be sold, or quantities go negative.
 */
class RequireOpeningLocked
{
    public function __construct(
        private PeriodLockService $periodLock,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->periodLock->isOpeningLocked()) {
            return redirect()
                ->route('opening.index')
                ->with('warning', __('accounting.errors.opening_not_complete'));
        }

        return $next($request);
    }
}
