<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\Account;
use Modules\Asset\Models\AssetCategory;

/**
 * Manage the asset-category list. Each category maps to a fixed-asset chart
 * account. Gated on master.manage; the store action returns JSON for the inline
 * "+ add category" overlay on the asset create form.
 */
class AssetCategoryController extends Controller
{
    public function index(): View
    {
        return view('shop.asset-category.index', [
            'categories' => AssetCategory::with('account')->orderBy('sort')->orderBy('name_bn')->get(),
            'accounts' => Account::where('type', 'asset')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name_bn' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        // Custom categories with no account chosen fall back to Other Fixed Assets.
        $data['account_id'] ??= Account::where('code', '1560')->value('id');
        $data['is_active'] = true;

        $category = AssetCategory::create($data);

        if ($request->boolean('inline')) {
            return response()->json([
                'id' => $category->id,
                'name' => $category->name,
            ]);
        }

        return redirect()->route('asset-categories.index')->with('status', __('ui.common.saved'));
    }

    public function destroy(AssetCategory $asset_category): RedirectResponse
    {
        if ($asset_category->is_system || $asset_category->assets()->exists()) {
            return back()->with('warning', __('asset.categories.in_use'));
        }

        $asset_category->delete();

        return redirect()->route('asset-categories.index')->with('status', __('ui.common.saved'));
    }
}
