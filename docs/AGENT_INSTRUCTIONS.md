
# Agent Instructions — Shop Accounting System

Read this file completely before writing any code in this project.

These files implement the accounting foundation. Everything else (sales,
purchases, expenses, reports) must be built on top of it **without changing
it**. The rules below are not style preferences — breaking any of them
silently corrupts the books in a way that will not be noticed for months.

---

## The one invariant

> **Total debits must equal total credits, at every moment, after every operation.**

There is a test for this: `tests/Feature/OpeningBalanceTest.php`.
Run it after every change:

```bash
php artisan test --filter=OpeningBalanceTest
```

If it fails, stop and fix it before writing anything else.

---

## Hard rules

### 1. Never write to the ledger directly

`journal_entries` and `journal_entry_lines` are written **only** by
`LedgerService::post()`. No controller, model, observer, seeder or command
may insert into them.

```php
// ❌ NEVER
JournalEntry::create([...]);
DB::table('journal_entry_lines')->insert([...]);

// ✅ ALWAYS
app(LedgerService::class)->post(date: ..., lines: [...]);
```

### 2. Never UPDATE or DELETE ledger rows or stock movements

These four tables are append-only:

- `journal_entries`
- `journal_entry_lines`
- `stock_movements`
- `opening_party_balances` (only `reversed_at` / `reversal_reason` may be set)

To correct a mistake, post a **reversing entry**:

```php
app(LedgerService::class)->reverse($originalEntry, 'reason for correction');
```

### 3. Never store derived values

These columns must **not** be added, ever:

| Forbidden column | Get it from instead |
|---|---|
| `products.current_stock` | `$product->currentStock()` |
| `products.opening_qty` | `stock_movements` where `reference_type='Opening'` |
| `accounts.balance` | `LedgerService::balance($account)` |
| `accounts.opening_balance` | the Opening journal entry |
| `customers.due` / `customers.opening_balance` | ledger + `opening_party_balances` |
| `suppliers.due` / `suppliers.opening_balance` | same |

If a query is slow, add an index or a period-snapshot table. Do **not**
add a cached balance column.

### 4. Every write goes inside a transaction

```php
return DB::transaction(function () use ($data) {
    // stock movement + journal entry + records, all or nothing
});
```

### 5. Sales must freeze the cost price

When creating a sale line, copy the product's `cost_price` into
`sale_items.cost_price`. Never look it up later from `products`, or
historical profit will change whenever the cost changes.

### 6. Opening balances go through one service only

`OpeningEntryService::post()` handles customers, suppliers, products and
accounts. Do not write a second opening path. The contra account is always
`3010` (Owner's Equity) — this is what keeps the books balanced when
records are added one at a time.

Do not decide debit vs credit in calling code. `AccountType::increasesWithDebit()`
is the only place that rule lives.

### 7. Respect the period lock

`LedgerService::post()` already refuses to post into a locked period.
Do not bypass it. Do not add an `$force` flag.

---

## Account codes referenced in code

These are seeded by `ChartOfAccountsSeeder` with `is_system = true` and must
never be renamed, re-coded or deleted:

| Code | Account | Used by |
|---|---|---|
| `1010` | Cash in Hand | payments, expenses |
| `1030` | Accounts Receivable | customer opening, credit sales |
| `1040` | Inventory | product opening, purchases, COGS |
| `2010` | Accounts Payable | supplier opening, credit purchases |
| `3010` | Owner's Equity | **all opening balances** |
| `4010` | Sales Revenue | sales |
| `4020` | Sales Discount | sales with discount |
| `5010` | COGS | sales |
| `5110` | Stock Loss | stock adjustments |

---

## Journal templates for the modules still to be built

Follow these exactly.

### Sale (two separate entries)

Entry 1 — revenue:
```
Debit   Cash/Bank              (amount paid now)
Debit   1030 Receivable        (amount left on credit)
Debit   4020 Sales Discount    (discount given, if any)
Credit  4010 Sales Revenue     (gross total before discount)
```

Entry 2 — cost of goods sold:
```
Debit   5010 COGS              (Σ qty × frozen cost_price)
Credit  1040 Inventory         (same amount)
```

### Purchase
```
Debit   1040 Inventory         (goods value + landed costs)
Credit  Cash/Bank              (amount paid now)
Credit  2010 Payable           (amount left on credit)
```

### Expense
```
Debit   5xxx Expense account
Credit  Cash/Bank
```

### Payment received from customer
```
Debit   Cash/Bank
Credit  1030 Receivable
```

### Payment made to supplier
```
Debit   2010 Payable
Credit  Cash/Bank
```

### Account transfer
```
Debit   destination account
Credit  source account
```

### Sale return
```
Debit   4010 Sales Revenue     (reverse the revenue)
Credit  Cash/Bank or 1030      (refund or reduce their due)
Debit   1040 Inventory         (goods back in)
Credit  5010 COGS              (reverse the cost)
```

### Stock loss / damage
```
Debit   5110 Stock Loss
Credit  1040 Inventory
```
Plus a negative `stock_movements` row of type `adjustment`.

---

## Build order

Do not skip ahead — each step depends on the one before.

1. ✅ **Done:** accounts, periods, ledger, opening balances, master data
2. **Purchase module** — proves stock IN and weighted-average costing
3. **Sale module** — proves stock OUT, COGS and receivables
4. **Expense / Payment / Transfer** — small, all use `LedgerService::post()`
5. **Reports** — Trial Balance first (it validates everything else), then
   Balance Sheet, P&L, Day Book, Stock, Aging
6. **Returns and adjustments**
7. **Discounts, incentives, rebates**
8. **Roles and permissions** (spatie/laravel-permission)

---

## Still to write

These are referenced but not yet implemented. Build them in step 2–3:

- `App\Services\Inventory\CostingService` — weighted-average recalculation on
  every stock IN. Formula:
  `newCost = (oldQty × oldCost + inQty × inCost) ÷ (oldQty + inQty)`
- `App\Services\Inventory\InventoryService` — `stockIn()`, `stockOut()`,
  `checkAvailability()`
- `App\Http\Middleware\RequireOpeningLocked` — blocks sale/purchase/expense
  routes until the opening period is locked
- Policies: `CustomerPolicy`, `ProductPolicy`, `SalePolicy` — staff must not
  see `cost_price` or profit figures
- `User` model and `users` migration come from Laravel Breeze

---

## Setup commands

```bash
composer create-project laravel/laravel shop-accounts
cd shop-accounts
composer require laravel/breeze --dev && php artisan breeze:install blade
composer require spatie/laravel-permission barryvdh/laravel-dompdf

# copy this project's app/, config/, database/, tests/ over the top

# set the cut-off date BEFORE entering any data
echo "SHOP_CUTOFF_DATE=2026-07-31" >> .env

php artisan migrate
php artisan db:seed --class=ChartOfAccountsSeeder
php artisan test --filter=OpeningBalanceTest
```

---

## Definition of done for any new module

- [ ] All writes inside `DB::transaction()`
- [ ] All ledger writes via `LedgerService::post()`
- [ ] No new derived/cached columns
- [ ] A feature test that asserts `assertLedgerBalanced()` afterwards
- [ ] `php artisan test` passes
