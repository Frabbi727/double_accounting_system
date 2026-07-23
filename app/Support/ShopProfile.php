<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * The editable shop profile (FR-73): name, address, phone and logo. Stored in
 * the settings table; the name falls back to config('shop.name') when unset.
 */
class ShopProfile
{
    public static function name(): string
    {
        return Setting::get('shop.name') ?: config('shop.name', 'আমার দোকান');
    }

    public static function address(): ?string
    {
        return Setting::get('shop.address');
    }

    public static function phone(): ?string
    {
        return Setting::get('shop.phone');
    }

    /** Public URL of the uploaded logo, or null if none. */
    public static function logoUrl(): ?string
    {
        $path = Setting::get('shop.logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function logoPath(): ?string
    {
        return Setting::get('shop.logo');
    }
}
