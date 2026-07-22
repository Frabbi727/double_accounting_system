<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasUUID;

abstract class BaseModel extends Model
{
    use HasUUID;

    /**
     * The attributes that aren't mass assignable.
     * Guarding is managed through Request and Service level DTO validation.
     *
     * @var array<int, string>|bool
     */
    protected $guarded = [];
}
