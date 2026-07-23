<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ShopProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Shop profile & logo (FR-73). Gated on master.manage. Values persist in the
 * settings table and surface on the printed invoice and purchase bill.
 */
class ShopProfileController extends Controller
{
    public function edit()
    {
        return view('shop.settings.profile', [
            'name' => ShopProfile::name(),
            'address' => ShopProfile::address(),
            'phone' => ShopProfile::phone(),
            'logoUrl' => ShopProfile::logoUrl(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        Setting::put('shop.name', $data['name']);
        Setting::put('shop.address', $data['address'] ?? null);
        Setting::put('shop.phone', $data['phone'] ?? null);

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            $this->deleteExistingLogo();
        }
        if ($request->hasFile('logo')) {
            Setting::put('shop.logo', $request->file('logo')->store('logo', 'public'));
        } elseif ($request->boolean('remove_logo')) {
            Setting::put('shop.logo', null);
        }

        return redirect()->route('shop-profile.edit')->with('status', __('ui.common.saved'));
    }

    private function deleteExistingLogo(): void
    {
        $existing = ShopProfile::logoPath();
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }
    }
}
