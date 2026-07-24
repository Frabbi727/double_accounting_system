<?php

namespace Modules\Return\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\JournalEntry;
use Modules\Sale\Models\Sale;

/**
 * A product-return document made against an original sale invoice. Like every
 * document in this app it stores facts only; the actual refund / deduction /
 * receivable money effects live in the linked journal entries and are read
 * back from them, never stored as columns.
 *
 * @property int $id
 * @property string|null $return_no
 * @property int $sale_id
 * @property int|null $customer_id
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $reason
 * @property string|null $notes
 * @property string|null $deduction_type
 * @property string $deduction_value
 * @property int $refund_account_id
 * @property string $discount_policy
 * @property string $status
 * @property int|null $revenue_entry_id
 * @property int|null $cogs_entry_id
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SaleReturnItem> $items
 */
class SaleReturn extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'deduction_value' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    /** @return HasMany<SaleReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'refund_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function revenueEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'revenue_entry_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function cogsEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cogs_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Gross returned amount = Σ (qty × unit_price). A document fact. */
    public function returnedAmount(): float
    {
        return round((float) $this->items->sum(fn (SaleReturnItem $i) => $i->lineAmount()), 2);
    }

    /**
     * Cash actually refunded now — read from the ledger (the credit posted to
     * the refund account in the revenue entry), so money stays single-sourced.
     */
    public function refundPaid(): float
    {
        return $this->creditToAccountId($this->refund_account_id);
    }

    /** Deduction retained by the shop (credit to 4040 in the revenue entry). */
    public function deduction(): float
    {
        return $this->creditToCode('4040');
    }

    /** How much of the customer's due (1030) this return cleared. */
    public function receivableReduced(): float
    {
        return $this->creditToCode('1030');
    }

    /** Final refund owed to the customer = returned − deduction. */
    public function finalRefund(): float
    {
        return round($this->refundPaid() + $this->receivableReduced(), 2);
    }

    private function creditToCode(string $code): float
    {
        $entry = $this->revenueEntry;
        if (! $entry) {
            return 0.0;
        }

        return round((float) $entry->lines
            ->filter(fn ($l) => optional($l->account)->code === $code)
            ->sum('credit'), 2);
    }

    private function creditToAccountId(int $accountId): float
    {
        $entry = $this->revenueEntry;
        if (! $entry) {
            return 0.0;
        }

        return round((float) $entry->lines
            ->where('account_id', $accountId)
            ->sum('credit'), 2);
    }
}
