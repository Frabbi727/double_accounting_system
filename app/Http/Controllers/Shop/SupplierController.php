<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\SupplierService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Incentive\Models\PartyIncentive;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierService $suppliers,
        private ReportService $reports,
        private PeriodLockService $periodLock,
    ) {}

    public function index(): View
    {
        $suppliers = Supplier::orderBy('name')->get();

        return view('shop.supplier.index', [
            'suppliers' => $suppliers,
            // Live ledger due per supplier (0 once settled) — never the frozen opening.
            'dues' => $suppliers->mapWithKeys(
                fn (Supplier $s) => [$s->id => $this->reports->partyDue('supplier', $s->id)]
            ),
        ]);
    }

    /** Full history/statement for one supplier — reachable even at zero due. */
    public function show(Supplier $supplier): View
    {
        return view('shop.supplier.show', [
            'record' => $supplier,
            'statement' => $this->reports->partyStatement('supplier', $supplier->id),
            'incentives' => PartyIncentive::where('party_type', 'supplier')
                ->where('party_id', $supplier->id)->latest('date')->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('shop.supplier.create', [
            'openingLocked' => $this->periodLock->isOpeningLocked(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:suppliers,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_amount' => ['nullable', 'numeric', 'gt:0'],
            'opening_date' => ['nullable', 'date', 'before_or_equal:'.config('shop.cutoff_date')],
        ]);

        // Business already started → opening dues can't be posted (locked date).
        // Guide the owner instead of hitting a wall.
        if (! empty($data['opening_amount']) && $this->periodLock->isOpeningLocked()) {
            return back()->withInput()->with('warning', __('ui.opening.master_locked_note'));
        }

        if (! empty($data['opening_amount'])) {
            $data['opening_items'] = [[
                'amount' => $data['opening_amount'],
                'original_date' => $data['opening_date'] ?? config('shop.cutoff_date'),
            ]];
        }

        $this->suppliers->create($data);

        return redirect()->route('suppliers.index')->with('status', __('ui.common.saved'));
    }
}
