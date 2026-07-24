<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningSummaryService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\AccountService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Accounting\Services\Master\SupplierService;
use Modules\Asset\Models\Asset;
use Modules\Asset\Models\AssetCategory;
use Modules\Asset\Services\AssetService;

/**
 * Guided, step-by-step opening-balance setup for non-technical shopkeepers.
 *
 * This controller adds NO accounting logic of its own — it is a friendly shell
 * that walks the owner through the same master services used everywhere else
 * (AccountService, Customer/Supplier/ProductService, AssetService). Every
 * opening entry those services post is self-balancing (contra = Owner's Equity),
 * so the books stay balanced after each step and the owner can stop/resume any
 * time. The final "review" step reuses the existing lock confirmation + summary.
 */
class OpeningWizardController extends Controller
{
    /** The ordered journey. `welcome` is the intro; `review` ends at the lock. */
    private const STEPS = ['welcome', 'cash', 'suppliers', 'customers', 'products', 'assets', 'review'];

    /** Steps a shopkeeper actually enters data on (welcome/review are chrome). */
    private const DATA_STEPS = ['cash', 'suppliers', 'customers', 'products', 'assets'];

    public function __construct(
        private PeriodLockService $periodLock,
        private OpeningSummaryService $summary,
        private LedgerService $ledger,
        private AccountService $accounts,
        private CustomerService $customers,
        private SupplierService $suppliers,
        private ProductService $products,
        private AssetService $assets,
    ) {}

    /** Entry point — the welcome/intro screen. */
    public function index(): View|RedirectResponse
    {
        // Already locked → nothing to set up; send them to the advanced page.
        if ($this->periodLock->isOpeningLocked()) {
            return redirect()->route('opening.index');
        }

        return view('shop.opening.setup.welcome', $this->chrome('welcome'));
    }

    /** Render one step of the wizard. */
    public function step(string $step): View|RedirectResponse
    {
        if ($this->periodLock->isOpeningLocked()) {
            return redirect()->route('opening.index');
        }

        if (! in_array($step, self::STEPS, true)) {
            return redirect()->route('opening.setup');
        }
        if ($step === 'welcome') {
            return redirect()->route('opening.setup');
        }

        return view("shop.opening.setup.$step", $this->chrome($step) + $this->stepData($step));
    }

    // ---------------------------------------------------------------------
    // Per-step quick-add handlers. Each saves via the shared service and
    // returns to the SAME step so the owner can add another, or press Next.
    // ---------------------------------------------------------------------

    /** Cash / bank / loan opening balances — one amount per existing account. */
    public function storeCash(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($data['amounts'] as $accountId => $amount) {
            $account = Account::find($accountId);
            if ($account === null) {
                continue;
            }

            $this->accounts->setOpening(
                $account,
                (float) ($amount ?? 0),
                __('ui.opening.wizard.cash_reason'),
            );
        }

        return redirect()->route('opening.setup.step', 'cash')
            ->with('status', __('ui.common.saved'));
    }

    /** A supplier the shop owes money to at opening. */
    public function storeSupplier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payload = ['name' => $data['name'], 'phone' => $data['phone'] ?? null];
        if (! empty($data['amount'])) {
            $payload['opening_items'] = [['amount' => $data['amount']]];
        }

        $this->suppliers->create($payload);

        return redirect()->route('opening.setup.step', 'suppliers')
            ->with('status', __('ui.common.saved'));
    }

    /** A customer who owes the shop money at opening. */
    public function storeCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'original_date' => ['nullable', 'date'],
        ]);

        $payload = ['name' => $data['name'], 'phone' => $data['phone'] ?? null];
        if (! empty($data['amount'])) {
            $payload['opening_items'] = [[
                'amount' => $data['amount'],
                'original_date' => $data['original_date'] ?? config('shop.cutoff_date'),
            ]];
        }

        $this->customers->create($payload);

        return redirect()->route('opening.setup.step', 'customers')
            ->with('status', __('ui.common.saved'));
    }

    /** A product plus its opening stock. */
    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'opening_qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->products->create([
            'name' => $data['name'],
            'unit' => $data['unit'] ?? 'pcs',
            'sale_price' => $data['sale_price'],
            'cost_price' => $data['cost_price'],
            'opening_qty' => $data['opening_qty'] ?? 0,
            'opening_cost' => $data['cost_price'],
        ]);

        return redirect()->route('opening.setup.step', 'products')
            ->with('status', __('ui.common.saved'));
    }

    /** An already-owned fixed asset (furniture, equipment, …). */
    public function storeAsset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_category_id' => ['required', 'exists:asset_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->assets->create([
            'asset_category_id' => $data['asset_category_id'],
            'name' => $data['name'],
            'amount' => $data['amount'],
            'payment_mode' => 'opening',
            'purchase_date' => config('shop.cutoff_date'),
        ]);

        return redirect()->route('opening.setup.step', 'assets')
            ->with('status', __('ui.common.saved'));
    }

    // ---------------------------------------------------------------------
    // View data
    // ---------------------------------------------------------------------

    /**
     * Shared wizard chrome: the ordered step list (with done-flags for the
     * progress checklist) plus prev/next navigation targets for `$current`.
     *
     * @return array<string, mixed>
     */
    private function chrome(string $current): array
    {
        $status = $this->stepStatus();
        $steps = [];

        foreach (self::STEPS as $i => $key) {
            $steps[] = [
                'key' => $key,
                'index' => $i,
                'label' => __("ui.opening.wizard.step.$key"),
                'url' => $key === 'welcome'
                    ? route('opening.setup')
                    : route('opening.setup.step', $key),
                'done' => $status[$key] ?? false,
                'is_data' => in_array($key, self::DATA_STEPS, true),
            ];
        }

        $currentIndex = array_search($current, self::STEPS, true);
        $prev = $currentIndex > 0 ? $steps[$currentIndex - 1] : null;
        $next = $currentIndex < count($steps) - 1 ? $steps[$currentIndex + 1] : null;

        return [
            'steps' => $steps,
            'current' => $current,
            'currentIndex' => $currentIndex,
            'totalSteps' => count($steps),
            'prevStep' => $prev,
            'nextStep' => $next,
        ];
    }

    /**
     * Whether each step has data yet — derived live, nothing stored.
     *
     * @return array<string, bool>
     */
    private function stepStatus(): array
    {
        $s = $this->summary->build();

        return [
            'welcome' => true,
            'cash' => abs($s['totals']['opening_cash']) > 0.005 || $s['accounts']['count'] > 0,
            'suppliers' => $s['suppliers']['count'] > 0,
            'customers' => $s['customers']['count'] > 0,
            'products' => $s['inventory']['product_count'] > 0,
            'assets' => Asset::where('status', 'active')->exists(),
            'review' => false,
        ];
    }

    /**
     * Step-specific data: the list of what has already been entered, plus any
     * inputs the form needs.
     *
     * @return array<string, mixed>
     */
    private function stepData(string $step): array
    {
        return match ($step) {
            'cash' => [
                'accounts' => Account::cashOrBank()->orderBy('code')->get()
                    ->map(fn (Account $a) => [
                        'model' => $a,
                        'balance' => round($this->ledger->balance($a), 2),
                    ]),
                // Loans are liabilities (money the shop borrowed) — shown in a
                // separate section of the same step so they can be entered too.
                'loans' => Account::where('subtype', 'loan')->orderBy('code')->get()
                    ->map(fn (Account $a) => [
                        'model' => $a,
                        'balance' => round($this->ledger->balance($a), 2),
                    ]),
            ],
            'suppliers' => [
                'rows' => Supplier::orderBy('name')->get()->map(fn (Supplier $s) => [
                    'name' => $s->name,
                    'amount' => (float) $s->openingBalances()->whereNull('reversed_at')->sum('amount'),
                ])->filter(fn ($r) => $r['amount'] > 0)->values(),
            ],
            'customers' => [
                'rows' => Customer::orderBy('name')->get()->map(fn (Customer $c) => [
                    'name' => $c->name,
                    'amount' => (float) $c->openingBalances()->whereNull('reversed_at')->sum('amount'),
                ])->filter(fn ($r) => $r['amount'] > 0)->values(),
            ],
            'products' => [
                'rows' => Product::where('is_active', true)->orderBy('name')->get()
                    ->map(function (Product $p) {
                        $m = $p->openingMovement();

                        return $m ? ['name' => $p->name, 'qty' => (float) $m->qty, 'unit' => $p->unit, 'cost' => (float) $m->unit_cost] : null;
                    })->filter()->values(),
            ],
            'assets' => [
                'categories' => AssetCategory::where('is_active', true)->orderBy('sort')->get(),
                'rows' => Asset::where('status', 'active')->latest('id')->get()
                    ->map(fn (Asset $a) => ['name' => $a->name, 'amount' => (float) $a->amount])->values(),
            ],
            'review' => [
                'summary' => $this->summary->build(),
            ],
            default => [],
        };
    }
}
