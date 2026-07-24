<?php

namespace Modules\Asset\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Asset\Models\Asset;
use Modules\Asset\Models\AssetCategory;

/**
 * Records a fixed-asset acquisition: one Asset record plus one balanced journal
 * entry. The debit is always the category's asset account. The credit depends on
 * how it was paid:
 *
 *   account : Cr Cash/Bank/MFS  (paid now)      — the "Furniture" example
 *   credit  : Cr Accounts Payable 2010          — unpaid, owed to a supplier
 *   opening : Cr Owner's Equity 3010            — already owned at setup
 *
 * Everything happens in one transaction, so the books and the asset record move
 * together and the ledger always balances.
 */
class AssetService
{
    private const PAYABLE_CODE = '2010';

    public function __construct(
        private LedgerService $ledger,
        private OpeningEntryService $opening,
    ) {}

    /**
     * Expected $data shape:
     *   asset_category_id, name, purchase_date, amount, payment_mode,
     *   payment_account_id?, supplier_id?, vendor_name?, reference_no?, description?
     *
     * @param  array<int, UploadedFile>  $documents
     */
    public function create(array $data, array $documents = []): Asset
    {
        return DB::transaction(function () use ($data, $documents) {

            $mode = $data['payment_mode'] ?? 'account';
            $amount = round((float) ($data['amount'] ?? 0), 2);
            $date = $data['purchase_date'] ?? now()->toDateString();

            if ($amount <= 0) {
                throw new \InvalidArgumentException(__('asset.errors.amount_positive'));
            }

            $category = AssetCategory::findOrFail($data['asset_category_id']);
            $assetAccount = $category->account;
            if ($assetAccount === null) {
                throw new \InvalidArgumentException(__('asset.errors.category_no_account'));
            }

            if ($mode === 'credit' && empty($data['supplier_id'])) {
                throw new \InvalidArgumentException(__('asset.errors.credit_needs_supplier'));
            }
            if ($mode === 'account' && empty($data['payment_account_id'])) {
                throw new \InvalidArgumentException(__('asset.errors.account_needs_payment'));
            }

            $asset = Asset::create([
                'asset_no' => 'TMP',                        // replaced below, id-derived
                'asset_category_id' => $category->id,
                'name' => $data['name'],
                'purchase_date' => $date,
                'amount' => $amount,
                'payment_mode' => $mode,
                'payment_account_id' => $mode === 'account' ? $data['payment_account_id'] : null,
                'supplier_id' => $mode === 'credit' ? $data['supplier_id'] : null,
                'vendor_name' => $data['vendor_name'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            $asset->update(['asset_no' => 'AST'.str_pad((string) $asset->id, 5, '0', STR_PAD_LEFT)]);

            // Post the double entry and remember the voucher.
            if ($mode === 'opening') {
                // OpeningEntryService debits the asset account (asset increases with
                // debit) and credits Owner's Equity 3010. allowMultiple: many assets.
                $entry = $this->opening->post(
                    account: $assetAccount,
                    amount: $amount,
                    date: $date,
                    source: $asset,
                    reference: $asset->asset_no,
                    allowMultiple: true,
                );
            } else {
                $creditAccount = $mode === 'credit'
                    ? $this->account(self::PAYABLE_CODE)
                    : Account::findOrFail($data['payment_account_id']);

                $entry = $this->ledger->post(
                    date: $date,
                    referenceType: 'AssetPurchase',
                    referenceId: $asset->id,
                    description: __('asset.description', ['name' => $asset->name, 'no' => $asset->asset_no]),
                    lines: [
                        ['account_id' => $assetAccount->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $amount],
                    ],
                );
            }

            $asset->update(['journal_entry_id' => $entry->id]);

            $this->storeDocuments($asset, $documents);

            return $asset->fresh(['category', 'documents']);
        });
    }

    /**
     * Dispose (write off) an asset: reverse its acquisition entry and mark it
     * disposed. Owner-only, append-only — the original entry is never edited.
     */
    public function dispose(Asset $asset, string $reason): Asset
    {
        return DB::transaction(function () use ($asset, $reason) {

            if ($asset->disposed()) {
                throw new \RuntimeException(__('asset.errors.already_disposed'));
            }

            if ($asset->journalEntry) {
                $this->ledger->reverse($asset->journalEntry, $reason);
            }

            $asset->update([
                'status' => 'disposed',
                'disposed_at' => now(),
                'disposed_by' => auth()->id(),
                'disposed_reason' => $reason,
            ]);

            return $asset->fresh();
        });
    }

    /**
     * @param  array<int, UploadedFile>  $documents
     */
    private function storeDocuments(Asset $asset, array $documents): void
    {
        foreach ($documents as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("assets/{$asset->id}", 'public');
            if (! is_string($path)) {
                continue;
            }

            $asset->documents()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
