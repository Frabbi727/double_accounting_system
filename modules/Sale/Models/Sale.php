<?php

namespace Modules\Sale\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Customer;

/**
 * A sale document. Money lives in the ledger (revenue, cash/receivable,
 * discount, COGS, inventory); this record holds only document facts. There
 * is NO stored total/due column — everything is derived.
 */
/**
 * @property int $id
 * @property int|null $customer_id
 * @property string|null $invoice_no
 * @property \Illuminate\Support\Carbon $date
 * @property string $discount
 * @property string $paid_amount
 * @property string|null $notes
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SaleItem> $items
 */
class Sale extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'discount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Gross revenue before discount (sum of line revenue). */
    public function gross(): float
    {
        return round((float) $this->items->sum(fn (SaleItem $i) => $i->lineRevenue()), 2);
    }

    /** Sum of per-line discounts. */
    public function itemDiscount(): float
    {
        return round((float) $this->items->sum(fn (SaleItem $i) => (float) $i->discount), 2);
    }

    /** Net after discount (per-line discounts + whole-bill discount). */
    public function net(): float
    {
        return round($this->gross() - $this->itemDiscount() - (float) $this->discount, 2);
    }

    /** Amount still owed by the customer (net − paid). */
    public function due(): float
    {
        return round($this->net() - (float) $this->paid_amount, 2);
    }

    /** Cost of goods sold, from the frozen line costs. */
    public function cogs(): float
    {
        return round((float) $this->items->sum(fn (SaleItem $i) => $i->lineCogs()), 2);
    }

    /** Gross profit = net revenue − COGS. */
    public function profit(): float
    {
        return round($this->net() - $this->cogs(), 2);
    }
}
