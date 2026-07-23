<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Finance\Services\ExpenseService;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseService $expenses,
    ) {}

    public function index()
    {
        return view('shop.expense.index', [
            'entries' => JournalEntry::where('reference_type', 'Expense')
                ->latest('date')->latest('id')->limit(50)->with('lines.account')->get(),
        ]);
    }

    public function create()
    {
        return view('shop.expense.create', [
            'expenseAccounts' => Account::where('type', 'expense')->orderBy('code')->get(),
            'paymentAccounts' => Account::cashOrBank()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['nullable', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->expenses->create($data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('expenses.index')->with('status', __('ui.common.saved'));
    }
}
