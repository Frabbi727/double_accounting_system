# Shop Accounting — Laravel Foundation Files

Drop-in files for a Laravel 11/12 project. Implements a double-entry
accounting core with inline opening balances.

## What's here

```
app/Enums/              AccountType (holds the debit/credit rule), MovementType
app/Exceptions/         Accounting exceptions
app/Models/             Account, JournalEntry, JournalEntryLine, AccountingPeriod,
                        Product, StockMovement, Customer, Supplier,
                        OpeningPartyBalance, ProductCategory
app/Services/Accounting/  LedgerService      <- the engine, read this first
                          OpeningEntryService <- one path for all opening balances
                          PeriodLockService
app/Services/Master/    CustomerService, SupplierService, ProductService, AccountService
app/Http/Requests/      StoreCustomerRequest, StoreProductRequest
config/shop.php         cut-off date and guards
database/migrations/    4 migrations
database/seeders/       ChartOfAccountsSeeder
tests/Feature/          OpeningBalanceTest  <- run this after every change
docs/                   AGENT_INSTRUCTIONS.md  <- give this to your agent
```

## Install

```bash
composer create-project laravel/laravel shop-accounts
cd shop-accounts
composer require laravel/breeze --dev && php artisan breeze:install blade
composer require spatie/laravel-permission barryvdh/laravel-dompdf

# copy app/ config/ database/ tests/ docs/ from this bundle into the project

echo "SHOP_CUTOFF_DATE=2026-07-31" >> .env   # set this BEFORE entering data

php artisan migrate
php artisan db:seed --class=ChartOfAccountsSeeder
php artisan test --filter=OpeningBalanceTest
```

## Not included

- `users` table / User model — comes from Breeze
- Sale, Purchase, Expense, Payment modules — see docs/AGENT_INSTRUCTIONS.md
  for the exact journal template each one must post
- CostingService, InventoryService — build with the Purchase module
- Blade views and controllers

## Two things to know before you start

1. **Set `SHOP_CUTOFF_DATE` first.** Every opening entry is dated on it.
   Changing it later means reversing every opening entry.

2. **These files were syntax-checked but not run.** No Laravel runtime was
   available in the environment they were written in. Expect to fix small
   integration issues (namespace paths, the User factory in tests) on first
   `php artisan test`.
