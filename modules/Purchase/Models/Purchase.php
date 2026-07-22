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
class Purchase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'landed_cost' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

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
