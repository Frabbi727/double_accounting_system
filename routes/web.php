<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\BackupController;
use App\Http\Controllers\Shop\CustomerController;
use App\Http\Controllers\Shop\DashboardController;
use App\Http\Controllers\Shop\ExpenseController;
use App\Http\Controllers\Shop\ExportController;
use App\Http\Controllers\Shop\IncentiveController;
use App\Http\Controllers\Shop\OpeningController;
use App\Http\Controllers\Shop\PaymentController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\PurchaseController;
use App\Http\Controllers\Shop\PurchaseReturnController;
use App\Http\Controllers\Shop\RebateController;
use App\Http\Controllers\Shop\ReportController;
use App\Http\Controllers\Shop\SaleController;
use App\Http\Controllers\Shop\SaleReturnController;
use App\Http\Controllers\Shop\StockAdjustmentController;
use App\Http\Controllers\Shop\ShopProfileController;
use App\Http\Controllers\Shop\SupplierController;
use App\Http\Controllers\Shop\UserController;
use App\Http\Controllers\Shop\TransferController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Toggle the UI language (bn/en). The chosen locale is remembered in the
// session and applied by the SetLocale middleware on every request.
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['bn', 'en'], true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Opening balance dashboard + lock (owner only).
    Route::middleware('can:opening.manage')->group(function () {
        Route::get('/opening', [OpeningController::class, 'index'])->name('opening.index');
        Route::post('/opening/lock', [OpeningController::class, 'lock'])->name('opening.lock');
    });

    // Master data (owner + accountant).
    Route::middleware('can:master.manage')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');

        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::get('/accounts/{account}/statement', [AccountController::class, 'statement'])->name('accounts.statement');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    });

    // Sales — needs sale.create and a locked opening period.
    Route::middleware(['can:sale.create', 'opening.locked'])->group(function () {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    });

    // Printing an invoice only needs sale.create (no opening lock) — view only.
    Route::middleware('can:sale.create')->group(function () {
        Route::get('/sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
    });

    // Purchases — needs purchase.create and a locked opening period.
    Route::middleware(['can:purchase.create', 'opening.locked'])->group(function () {
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    });

    // Printing a purchase bill only needs purchase.create (no opening lock) — view only.
    Route::middleware('can:purchase.create')->group(function () {
        Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
    });

    // Expenses (owner + accountant).
    Route::middleware(['can:expense.create', 'opening.locked'])->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });

    // Payments & transfers (owner + accountant).
    Route::middleware(['can:payment.manage', 'opening.locked'])->group(function () {
        Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');

        // Incentives — income received / commission paid (owner + accountant).
        Route::get('/incentives', [IncentiveController::class, 'index'])->name('incentives.index');
        Route::get('/incentives/create', [IncentiveController::class, 'create'])->name('incentives.create');
        Route::post('/incentives', [IncentiveController::class, 'store'])->name('incentives.store');
    });

    // Returns & stock adjustments (owner only — corrections, §3.1).
    Route::middleware(['can:entry.delete', 'opening.locked'])->group(function () {
        Route::get('/returns/sale', [SaleReturnController::class, 'create'])->name('returns.sale');
        Route::post('/returns/sale', [SaleReturnController::class, 'store'])->name('returns.sale.store');

        Route::get('/returns/purchase', [PurchaseReturnController::class, 'create'])->name('returns.purchase');
        Route::post('/returns/purchase', [PurchaseReturnController::class, 'store'])->name('returns.purchase.store');

        Route::get('/stock-loss', [StockAdjustmentController::class, 'create'])->name('stock_loss.create');
        Route::post('/stock-loss', [StockAdjustmentController::class, 'store'])->name('stock_loss.store');

        // Rebate — lowers a product's cost (owner only, inventory valuation).
        Route::get('/rebates/create', [RebateController::class, 'create'])->name('rebates.create');
        Route::post('/rebates', [RebateController::class, 'store'])->name('rebates.store');
    });

    // Reports (owner + accountant). Cost/profit columns additionally gated in-view.
    Route::middleware('can:report.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial_balance');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance_sheet');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit_loss');
        Route::get('/day-book', [ReportController::class, 'dayBook'])->name('day_book');
        Route::get('/cash-book', [ReportController::class, 'cashBook'])->name('cash_book');
        Route::get('/stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('/low-stock', [ReportController::class, 'lowStock'])->name('low_stock');
        Route::get('/customer-due', [ReportController::class, 'customerDue'])->name('customer_due');
        Route::get('/supplier-due', [ReportController::class, 'supplierDue'])->name('supplier_due');
        Route::get('/aging', [ReportController::class, 'aging'])->name('aging');
        Route::get('/party-statement', [ReportController::class, 'partyStatement'])->name('party_statement');
        Route::get('/product-profit', [ReportController::class, 'productProfit'])->name('product_profit');
        Route::get('/audit-log', [ReportController::class, 'auditLog'])->name('audit_log');

        // Export (CSV / PDF) — ?format=csv|pdf.
        Route::get('/export/trial-balance', [ExportController::class, 'trialBalance'])->name('export.trial_balance');
        Route::get('/export/stock', [ExportController::class, 'stock'])->name('export.stock');
        Route::get('/export/account-statement/{account}', [ExportController::class, 'accountStatement'])->name('export.account_statement');
    });

    // Shop profile & logo (owner + accountant — master.manage).
    Route::middleware('can:master.manage')->group(function () {
        Route::get('/shop-profile', [ShopProfileController::class, 'edit'])->name('shop-profile.edit');
        Route::post('/shop-profile', [ShopProfileController::class, 'update'])->name('shop-profile.update');
    });

    // Data backup (owner only).
    Route::middleware('can:backup.manage')->group(function () {
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::get('/backup/download', [BackupController::class, 'download'])->name('backup.download');
    });

    // User management (owner only).
    Route::middleware('can:user.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
