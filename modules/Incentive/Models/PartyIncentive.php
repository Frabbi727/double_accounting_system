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
/**
 * @property int $id
 * @property string $kind
 * @property string $direction
 * @property string|null $party_type
 * @property int|null $party_id
 * @property string $basis
 * @property string|null $rate
 * @property string|null $base_amount
 * @property string $amount
 * @property int|null $product_id
 * @property string|null $ref_doc_type
 * @property int|null $ref_doc_id
 * @property string $settle_mode
 * @property int|null $settle_account_id
 * @property \Illuminate\Support\Carbon|null $period_from
 * @property \Illuminate\Support\Carbon|null $period_to
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $notes
 * @property int $journal_entry_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function settleAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'settle_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
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

        $res = $this->party_type === 'customer'
            ? Customer::find($this->party_id)
            : Supplier::find($this->party_id);

        return $res instanceof Model ? $res : null;
    }
}
