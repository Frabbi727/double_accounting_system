<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ReturnPolicy;
use App\Support\ShopProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Shop profile & logo (FR-73). Gated on master.manage. Values persist in the
 * settings table and surface on the printed invoice and purchase bill.
 */
class ShopProfileController extends Controller
{
    public function edit(): View
    {
        return view('shop.settings.profile', [
            'name' => ShopProfile::name(),
            'address' => ShopProfile::address(),
            'phone' => ShopProfile::phone(),
            'logoUrl' => ShopProfile::logoUrl(),
            'returnDiscountPolicy' => ReturnPolicy::discountPolicy(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'return_discount_policy' => ['nullable', 'in:'.implode(',', ReturnPolicy::options())],
        ]);

        Setting::put('shop.name', $data['name']);
        Setting::put('shop.address', $data['address'] ?? null);
        Setting::put('shop.phone', $data['phone'] ?? null);
        if (! empty($data['return_discount_policy'])) {
            Setting::put(ReturnPolicy::KEY, $data['return_discount_policy']);
        }

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            $this->deleteExistingLogo();
        }
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')?->store('logo', 'public');
            if (is_string($path)) {
                Setting::put('shop.logo', $path);
            }
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
