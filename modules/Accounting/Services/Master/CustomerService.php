<?php

namespace Modules\Accounting\Services\Master;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\OpeningPartyBalance;
use Modules\Accounting\Services\Accounting\OpeningEntryService;

class CustomerService
{
    public function __construct(
        private OpeningEntryService $opening,
    ) {}

    /**
     * Create a customer, optionally with opening dues entered on the same form.
     *
     * Expected $data shape:
     *   name, phone, address, credit_limit, default_discount_percent
     *   opening_items => [ ['amount'=>.., 'original_date'=>..., 'reference'=>...], ... ]
     *
     * A single opening amount is just an opening_items array with one row.
     */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {

            $customer = Customer::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'credit_limit' => $data['credit_limit'] ?? 0,
                'default_discount_percent' => $data['default_discount_percent'] ?? 0,
            ]);

            foreach ($data['opening_items'] ?? [] as $item) {
                $this->addOpeningItem($customer, $item);
            }

            return $customer->fresh();
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        // Opening fields are deliberately NOT updatable here.
        // Use correctOpening() so a reversal is recorded.
        $customer->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'default_discount_percent' => $data['default_discount_percent'] ?? 0,
        ]);

        return $customer->fresh();
    }

    /**
     * Post one opening due line (one old unpaid invoice, or one lump sum).
     *
     * Journal:  Debit  1030 Accounts Receivable
     *           Credit 3010 Owner's Equity
     */
    public function addOpeningItem(Customer $customer, array $item): OpeningPartyBalance
    {
        $amount = round((float) $item['amount'], 2);
        $originalDate = $item['original_date'] ?? config('shop.cutoff_date');
        $reference = $item['reference'] ?? null;

        $entry = $this->opening->post(
            account: $this->opening->receivableAccount(),
            amount: $amount,
            // The journal is dated at the cut-off, not the original invoice date —
            // otherwise the opening period boundary would be violated.
            date: config('shop.cutoff_date'),
            source: $customer,
            reference: $reference,
            // A customer may carry several old unpaid invoices.
            allowMultiple: true,
        );

        return OpeningPartyBalance::create([
            'party_type' => Customer::class,
            'party_id' => $customer->id,
            'amount' => $amount,
            'original_date' => $originalDate,   // real age, for the aging report
            'reference' => $reference,
            'journal_entry_id' => $entry->id,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Correct an opening balance. Reverses the old journal entry and posts a
     * new one — the original is preserved for audit.
     */
    public function correctOpening(Customer $customer, float $newAmount, string $reason): void
    {
        DB::transaction(function () use ($customer, $newAmount, $reason) {

            $this->opening->correct(
                source: $customer,
                account: $this->opening->receivableAccount(),
                newAmount: $newAmount,
                date: config('shop.cutoff_date'),
                reason: $reason,
            );

            $customer->openingBalances()->whereNull('reversed_at')->update([
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);
        });
    }

    /** Find a customer with a confusingly similar name, to warn the user. */
    public function findSimilar(string $name, ?int $excludeId = null): ?Customer
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));

        return Customer::where('name_normalized', $normalized)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }
}
