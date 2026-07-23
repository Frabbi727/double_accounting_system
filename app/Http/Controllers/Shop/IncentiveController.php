<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Incentive\Services\IncentiveService;

/**
 * Incentives (FR-49/50): a bonus received from a supplier is our income,
 * a commission paid out is our expense. Ledger-only via IncentiveService.
 */
class IncentiveController extends Controller
{
    public function __construct(
        private IncentiveService $incentives,
    ) {}

    public function index()
    {
        return view('shop.incentive.index', [
            'entries' => JournalEntry::whereIn('reference_type', ['IncentiveIn', 'IncentiveOut'])
                ->latest('date')->latest('id')->limit(50)->with('lines.account')->get(),
        ]);
    }

    public function create()
    {
        return view('shop.incentive.create', [
            'accounts' => Account::cashOrBank()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:received,paid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'amount' => $data['amount'],
            'date' => $data['date'],
            'account_id' => $data['account_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        try {
            $data['direction'] === 'received'
                ? $this->incentives->receive($payload)
                : $this->incentives->pay($payload);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('incentives.index')->with('status', __('ui.common.saved'));
    }
}
