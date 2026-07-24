<?php

namespace Modules\Asset\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A supporting document for an asset (invoice, receipt, warranty, image),
 * stored on the public disk.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int|null $size
 * @property int|null $uploaded_by
 */
class AssetDocument extends Model
{
    protected $guarded = [];

    /** Public URL for the stored file. */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** True when the file is a displayable image. */
    public function isImage(): bool
    {
        return is_string($this->mime) && str_starts_with($this->mime, 'image/');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
