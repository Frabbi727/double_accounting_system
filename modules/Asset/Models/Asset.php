<?php

namespace Modules\Asset\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Supplier;

/**
 * A capital asset the shop owns. The money is recorded in the ledger (asset
 * account debited, cash/bank/payable/equity credited); this record holds the
 * document facts and links to the journal entry that is its voucher.
 *
 * @property int $id
 * @property string $asset_no
 * @property int $asset_category_id
 * @property string $name
 * @property \Illuminate\Support\Carbon $purchase_date
 * @property string $amount
 * @property string $payment_mode
 * @property int|null $payment_account_id
 * @property int|null $supplier_id
 * @property string|null $vendor_name
 * @property string|null $reference_no
 * @property string|null $description
 * @property int|null $journal_entry_id
 * @property string $status
 * @property int|null $created_by
 */
class Asset extends Model
{
    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'amount' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'disposed_at' => 'datetime',
    ];

    public function disposed(): bool
    {
        return $this->status === 'disposed';
    }

    /** The vendor label: master supplier name, else the free-text vendor. */
    public function vendorLabel(): ?string
    {
        return $this->supplier?->name ?? $this->vendor_name;
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return HasMany<AssetDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }
}
