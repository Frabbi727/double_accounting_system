<?php

namespace Modules\Accounting\Services\Master;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\OpeningPartyBalance;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\OpeningEntryService;

/**
 * Mirror image of CustomerService.
 *
 * The only real difference: Accounts Payable (2010) is a LIABILITY, so
 * OpeningEntryService automatically credits it and debits equity — the
 * opposite of a customer. No branching needed here.
 */
class SupplierService
{
    public function __construct(
        private OpeningEntryService $opening,
    ) {}

    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {

            $supplier = Supplier::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'payment_term_days' => $data['payment_term_days'] ?? 0,
            ]);

            foreach ($data['opening_items'] ?? [] as $item) {
                $this->addOpeningItem($supplier, $item);
            }

            return $supplier->fresh();
        });
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'payment_term_days' => $data['payment_term_days'] ?? 0,
        ]);

        return $supplier->fresh();
    }

    /**
     * Journal:  Debit  3010 Owner's Equity
     *           Credit 2010 Accounts Payable
     */
    public function addOpeningItem(Supplier $supplier, array $item): OpeningPartyBalance
    {
        $amount = round((float) $item['amount'], 2);
        $originalDate = $item['original_date'] ?? config('shop.cutoff_date');
        $reference = $item['reference'] ?? null;

        $entry = $this->opening->post(
            account: $this->opening->payableAccount(),
            amount: $amount,
            date: config('shop.cutoff_date'),
            source: $supplier,
            reference: $reference,
            // A supplier may carry several old unpaid bills.
            allowMultiple: true,
        );

        return OpeningPartyBalance::create([
            'party_type' => Supplier::class,
            'party_id' => $supplier->id,
            'amount' => $amount,
            'original_date' => $originalDate,
            'reference' => $reference,
            'journal_entry_id' => $entry->id,
            'created_by' => auth()->id(),
        ]);
    }

    public function correctOpening(Supplier $supplier, float $newAmount, string $reason): void
    {
        DB::transaction(function () use ($supplier, $newAmount, $reason) {

            $this->opening->correct(
                source: $supplier,
                account: $this->opening->payableAccount(),
                newAmount: $newAmount,
                date: config('shop.cutoff_date'),
                reason: $reason,
            );

            $supplier->openingBalances()->whereNull('reversed_at')->update([
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);
        });
    }

    public function findSimilar(string $name, ?int $excludeId = null): ?Supplier
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));

        return Supplier::where('name_normalized', $normalized)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }
}
