<?php

namespace Modules\Incentive\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\Supplier;

/**
 * A single incentive / rebate event — the business metadata behind the ledger
 * entry it posted. See the migration for the "why". "How much is still owed"
 * is never stored here; it is derived from the ledger via ReportService.
 */
class PartyIncentive extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function settleAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'settle_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the attributed party (Customer or Supplier) from the string
     * party_type — we store a plain 'customer'/'supplier' tag rather than a
     * polymorphic class name, to match ReportService::partyDue().
     */
    public function party(): ?Model
    {
        if (! $this->party_id) {
            return null;
        }

        return $this->party_type === 'customer'
            ? Customer::find($this->party_id)
            : Supplier::find($this->party_id);
    }
}
