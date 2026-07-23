# Progress Tracker — Shop Accounting App

একটা bilingual (বাংলা default / English) double-entry accounting app, দোকানের জন্য।
এই ফাইলটা কাজের অগ্রগতির লগ — পরে যেখান থেকে থেমেছি সেখান থেকে শুরু করার জন্য।

শেষ আপডেট: 2026-07-23 (Discounts/Incentives/Rebate সহ)

---

## মূল সিদ্ধান্ত (confirmed)

- **Structure**: accounting কোড `modules/Accounting`-এ (existing `Modules\Core` scaffold অনুসরণ)। Purchase আলাদা `modules/Purchase`। Module provider auto-register হয় `Modules\Core\Providers\ModuleAutoloadServiceProvider` দিয়ে।
- **ID**: accounting টেবিলে সাধারণ auto-increment **int** (Core-এর UUID নয়)।
- **Bilingual**: UI/message → `lang/bn` + `lang/en` (`__()` key); seeded data (account/category নাম) → `name_bn` + `name_en` কলাম + locale-aware `name` accessor। `APP_LOCALE=bn`। `SetLocale` middleware + `/locale/{locale}` route toggle।
- **Source**: proven bundle `../shop-accounting-laravel` থেকে port করা হচ্ছে (int ID, hardcoded Bangla) → adapt + bilingual।
- **নিয়ম**: `docs/AGENT_INSTRUCTIONS.md` — total debit == total credit সবসময়; শুধু `LedgerService::post()` ledger-এ লেখে; ledger/stock append-only; derived কলাম নয়।

---

## ✅ ধাপ ১ — Foundation (DONE, tested)

`modules/Accounting/`: Enums (AccountType, MovementType), 5 Exception, 10 Model, Services/Accounting (LedgerService, OpeningEntryService, PeriodLockService), Services/Master (Account/Customer/Product/Supplier), Http/Requests (2), 4 migration।
Project: `config/shop.php`, `lang/bn|en/accounting.php`, `database/seeders/ChartOfAccountsSeeder.php` (২৮ account bn+en), `app/Http/Middleware/SetLocale.php`, `/locale/{locale}` route, `.env` (APP_LOCALE=bn, SHOP_*)।

- টেস্ট: `tests/Feature/OpeningBalanceTest.php` — **৮/৮ পাস**
- Fix: `OpeningEntryService::post()`-এ `allowMultiple` ফ্ল্যাগ (party একাধিক invoice রাখতে পারে; account/product single)।

## ✅ ধাপ ২ — Purchase (DONE, tested)

`modules/Accounting/Services/Inventory/`: `CostingService` (weighted-average), `InventoryService` (stockIn/stockOut/checkAvailability + negative-stock guard)।
`modules/Purchase/`: Provider, Models (Purchase, PurchaseItem), `PurchaseService`, migration (`2026_08_02_000001_create_purchases_tables`)। `lang/bn|en/purchase.php`।
Journal: Debit 1040 Inventory / Credit Cash/Bank / Credit 2010 Payable। Landed cost inventory-এ capitalize + value অনুপাতে apportion (inventory ledger == summed stock value বজায় থাকে)।

- টেস্ট: `tests/Feature/PurchaseTest.php` — **৩/৩ পাস** (weighted-avg, landed cost, insufficient-stock reject)

## ✅ ধাপ ৩ — Sale (DONE, tested)

`modules/Sale/`: Provider, Models (Sale, SaleItem), `SaleService`, migration (`2026_08_03_000001_create_sales_tables`)। `lang/bn|en/sale.php`।
দুই journal: (১) revenue — Debit Cash/1030 Receivable/4020 Discount, Credit 4010 Sales (gross); (২) COGS — Debit 5010, Credit 1040। প্রতি লাইনে `InventoryService::stockOut` (availability guard) + `cost_price` **freeze** (sale_items-এ বর্তমান weighted-avg cost কপি; পরে product cost বদলালেও historical COGS বদলায় না)।

- টেস্ট: `tests/Feature/SaleTest.php` — **৪/৪ পাস** (cash sale, credit sale + discount, cost freeze, insufficient-stock reject)

**বর্তমানে পুরো suite: ১৮/১৮ পাস।**

## ✅ ধাপ ৪ — Expense / Payment / Transfer (DONE, tested)

`modules/Finance/`: Provider + Services (`ExpenseService`, `PaymentService`, `TransferService`)। কোনো নতুন টেবিল নেই — single-amount লেনদেন, journal entry-ই রেকর্ড (reference_type: Expense / PaymentIn / PaymentOut / Transfer; party payment-এ reference_id = customer/supplier id)। `lang/bn|en/finance.php`।
- Expense: Debit 5xxx খরচ (type=expense যাচাই), Credit Cash/Bank
- Payment received: Debit Cash/Bank, Credit 1030
- Payment made: Debit 2010, Credit Cash/Bank
- Transfer: Debit destination, Credit source (same-account reject)

- টেস্ট: `tests/Feature/FinanceTest.php` — **৬/৬ পাস**

**বর্তমানে পুরো suite: ২৪/২৪ পাস।**

## ✅ ধাপ ৫ — Reports (DONE, tested)

`modules/Accounting/Services/Reporting/ReportService.php` — সব figure ledger/movement/subsidiary থেকে derived (কোনো cached value নয়):
- `balanceSheet()` — Assets = Liabilities + Equity (চলতি মেয়াদের profit equity-তে ভাঁজ করা, তাই সবসময় মেলে)
- `profitAndLoss()` — income − expense; period range সাপোর্ট (`$from`)
- `dayBook($date)` — এক দিনের সব journal entry + line
- `stock()` — active product-এর qty ও value (== inventory ledger)
- `aging('customer'|'supplier')` — 0-30/31-60/61-90/90+ bucket (opening party balance-এর উপর)
- Trial Balance আগেই `LedgerService::trialBalance()`-এ আছে।

- টেস্ট: `tests/Feature/ReportTest.php` — **৫/৫ পাস** (BS balanced, P&L==BS profit, day book balanced, stock==ledger, aging bucket)

**বর্তমানে পুরো suite: ২৯/২৯ পাস।**

## ✅ ধাপ ৬ — Returns ও adjustments (DONE, tested)

`modules/Adjustment/Services/`: `SaleReturnService`, `PurchaseReturnService`, `StockAdjustmentService`। নতুন টেবিল নেই — reference দিয়ে original document-এ লিঙ্ক। `InventoryService`-এ নতুন `adjustOut()` (type=adjustment)। `lang/bn|en/adjustment.php`।
- Sale return: Debit 4010 Revenue, Credit Cash/1030; Debit 1040 Inventory (মূল frozen cost-এ ফেরত), Credit 5010 COGS
- Purchase return: Debit Cash/2010, Credit 1040 (current weighted-avg cost-এ)
- Stock loss: Debit 5110, Credit 1040 + negative adjustment movement

- টেস্ট: `tests/Feature/AdjustmentTest.php` — **৪/৪ পাস** (sale return, purchase return, stock loss, over-return reject)। প্রতিটাতে inventory ledger == stock value বজায় থাকে।

**বর্তমানে পুরো suite: ৩৩/৩৩ পাস।**

## ✅ ধাপ ৭ — Discounts / Incentives / Rebates (DONE, tested)

requirements-document-bn.md (FR-21, 47-53) মেনে:
- **Line-level discount** (FR-21): `sale_items.discount` কলাম (নতুন migration), `SaleService` line + bill discount দুটোই 4020-তে debit করে; revenue gross-এ credit থাকে। (4020 income-type হওয়ায় contra-revenue — natural balance ঋণাত্মক, আয় কমায়।)
- `modules/Incentive/Services/`: `IncentiveService` — receive (Debit Cash, Credit 4030 **আয়**) / pay (Debit 5100 **খরচ**, Credit Cash); `RebateService` — নির্দিষ্ট পণ্যের weighted-avg cost কমিয়ে 1040 credit (আয় নয়, **ক্রয়মূল্য কমায়**; invariant বজায়)। `lang/bn|en/incentive.php`।

- টেস্ট: `tests/Feature/IncentiveTest.php` — **৫/৫ পাস** (line+bill discount, incentive income, incentive expense, rebate cost-reduction, rebate>value reject)

**বর্তমানে পুরো suite: ৩৮/৩৮ পাস।**

---

## ⏭️ পরের ধাপ (এখনো বাকি — build order অনুযায়ী)

8. **Roles ও permissions + Blade UI** — spatie/laravel-permission, `RequireOpeningLocked` middleware, policies (staff cost_price/profit দেখবে না), আসল ব্যবহারযোগ্য UI (login + entry screens + reports)। **এখনো কোনো screen নেই — সব service স্তরে, test দিয়ে চালানো।**
6. **Returns ও adjustments**
7. **Discounts / incentives / rebates**
8. **Roles ও permissions** (spatie) + `RequireOpeningLocked` middleware + policies + Blade UI

## 🔧 খোলা টেকনিক্যাল কাজ (deferred, user-এর সম্মতিতে)

- **PHPStan**: `phpstan.neon` level 8, কিন্তু **larastan install করা নেই** (শুধু composer.lock-এ)। বেয়ার level-8-এ নতুন module-এ ~১৫৮টা Eloquent false-positive। ঠিক করতে: `composer require --dev larastan/larastan` + `phpstan.neon`-এ `includes: vendor/larastan/larastan/extension.neon`। (কাজ করা কোড ভাঙে না।)
- `journal_entries.description` তৈরির সময়ের locale-এ store হয় (milestone 1-এ গ্রহণযোগ্য tradeoff)।

## কীভাবে যাচাই করবে

```bash
composer dump-autoload
php artisan migrate:fresh --seed --seeder=Database\\Seeders\\ChartOfAccountsSeeder
php artisan test                 # ১৪/১৪ পাস হওয়া উচিত
./vendor/bin/pint modules        # code style
```
