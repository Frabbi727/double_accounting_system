<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Asset\Models\Asset;
use Modules\Asset\Models\AssetCategory;
use Modules\Asset\Models\AssetDocument;
use Modules\Asset\Services\AssetService;

/**
 * Asset Management (requirement §3). Capitalizes fixed assets with automatic
 * double-entry posting, full purchase provenance, supporting documents, voucher
 * and audit trail. Gated on asset.manage (owner + accountant).
 */
class AssetController extends Controller
{
    public function __construct(
        private AssetService $assets,
    ) {}

    /** The Asset Register. */
    public function index(): View
    {
        $assets = Asset::with(['category', 'supplier', 'paymentAccount'])
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(30);

        $activeTotal = (float) Asset::where('status', 'active')->sum('amount');

        return view('shop.asset.index', [
            'assets' => $assets,
            'activeTotal' => $activeTotal,
        ]);
    }

    public function create(): View
    {
        return view('shop.asset.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAsset($request);

        try {
            $asset = $this->assets->create($data, $request->file('documents') ?? []);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('assets.show', $asset)->with('status', __('ui.common.saved'));
    }

    /** The Asset Details page — info, purchase, payment, voucher, documents, audit. */
    public function show(Asset $asset): View
    {
        $asset->load([
            'category.account',
            'supplier',
            'paymentAccount',
            'creator',
            'disposer',
            'documents',
            'journalEntry.lines.account',
            'journalEntry.reversedBy.lines.account',
        ]);

        // For an on-credit asset, show the supplier's live remaining due (ledger-
        // derived, folded into AP). Null for paid/opening assets — nothing is owed.
        $supplierDue = ($asset->payment_mode === 'credit' && $asset->supplier_id && ! $asset->disposed())
            ? app(ReportService::class)->partyDue('supplier', $asset->supplier_id)
            : null;

        return view('shop.asset.show', [
            'asset' => $asset,
            'supplierDue' => $supplierDue,
        ]);
    }

    /** Edit metadata only — financial fields are immutable (dispose + re-create to correct). */
    public function edit(Asset $asset): View
    {
        $asset->load('documents');

        return view('shop.asset.edit', array_merge($this->formData(), ['asset' => $asset]));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'remove_documents' => ['nullable', 'array'],
            'remove_documents.*' => ['integer'],
        ]);

        $asset->update([
            'name' => $data['name'],
            'reference_no' => $data['reference_no'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        // Remove selected documents.
        foreach ($asset->documents()->whereIn('id', $data['remove_documents'] ?? [])->get() as $doc) {
            $this->deleteDocument($doc);
        }

        // Add new uploads.
        foreach ($request->file('documents') ?? [] as $file) {
            $path = $file->store("assets/{$asset->id}", 'public');
            if (is_string($path)) {
                $asset->documents()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return redirect()->route('assets.show', $asset)->with('status', __('ui.common.saved'));
    }

    public function dispose(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'dispose_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->assets->dispose($asset, $data['dispose_reason']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return redirect()->route('assets.show', $asset)->withErrors(['dispose' => $e->getMessage()]);
        }

        return redirect()->route('assets.show', $asset)->with('status', __('ui.common.saved'));
    }

    /**
     * @return array{name:string, purchase_date:string, amount:float, payment_mode:string, ...}
     */
    private function validateAsset(Request $request): array
    {
        return $request->validate([
            'asset_category_id' => ['required', 'exists:asset_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', Rule::in(['account', 'credit', 'opening'])],
            'payment_account_id' => ['nullable', 'required_if:payment_mode,account', 'exists:accounts,id'],
            'supplier_id' => ['nullable', 'required_if:payment_mode,credit', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'categories' => AssetCategory::where('is_active', true)->orderBy('sort')->orderBy('name_bn')->get(),
            'paymentAccounts' => Account::cashOrBank()->where('is_active', true)->orderBy('code')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'defaultAccountId' => Account::where('code', '1010')->value('id'),
        ];
    }

    private function deleteDocument(AssetDocument $doc): void
    {
        if (Storage::disk('public')->exists($doc->path)) {
            Storage::disk('public')->delete($doc->path);
        }
        $doc->delete();
    }
}
