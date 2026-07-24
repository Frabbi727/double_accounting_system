<?php

namespace Modules\Purchase\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Supplier;

/**
 * A purchase document. Its money is recorded in the ledger (inventory,
 * cash/bank, payable); this record only holds the document facts. There is
 * NO stored total/due column — both are derived from the items and the
 * ledger, so nothing can drift.
 */
/**
 * @property int $id
 * @property int|null $supplier_id
 * @property string|null $invoice_no
 * @property \Illuminate\Support\Carbon $date
 * @property string $landed_cost
 * @property string $paid_amount
 * @property string|null $notes
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PurchaseItem> $items
 */
class Purchase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'landed_cost' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Goods value only (sum of line totals), before landed cost. */
    public function subtotal(): float
    {
        return round((float) $this->items->sum(fn (PurchaseItem $i) => $i->lineTotal()), 2);
    }

    /** Full invoice value: goods + landed cost. */
    public function total(): float
    {
        return round($this->subtotal() + (float) $this->landed_cost, 2);
    }

    /** Amount still owed on this document (total − paid). */
    public function due(): float
    {
        return round($this->total() - (float) $this->paid_amount, 2);
    }
}
