# দোকানের হিসাব সফটওয়্যার — Laravel দিয়ে বানানোর সম্পূর্ণ গাইড

এই ডকুমেন্টে আপনি পাবেন একটা **প্রকৃত অ্যাকাউন্টিং সিস্টেম** (double-entry bookkeeping ভিত্তিক) বানানোর জন্য সম্পূর্ণ স্থাপত্য (architecture), ডেটাবেস স্কিমা, মডেল, সার্ভিস লজিক এবং ধাপে ধাপে রোডম্যাপ। এটা টালি/QuickBooks-এর মতো সফটওয়্যারগুলো যেভাবে কাজ করে সেই ভিত্তির উপর তৈরি — যাতে ভবিষ্যতে যত ফিচার যোগ করেন না কেন, হিসাব সবসময় নিজে থেকে মিলে যায় (self-balancing)।

---

## ১. কেন Double-Entry Bookkeeping?

আগের সহজ ভার্সনে (React আর্টিফ্যাক্ট) প্রতিটা মডিউল (বিক্রয়, ক্রয়, খরচ...) আলাদাভাবে হিসাব রাখছিল, আর ব্যালেন্স হিসাব হচ্ছিল সব রেকর্ড লুপ করে। ছোট দোকানের জন্য এটা কাজ করে, কিন্তু এতে সমস্যা হলো:

- হিসাব ভুল হলে ধরা কঠিন (কোথায় গরমিল, বোঝা যায় না)
- Balance Sheet বা Trial Balance বানানো যায় না
- Audit trail দুর্বল

**Double-entry** এর নিয়ম একটাই: **প্রতিটা লেনদেনে Debit = Credit**। এতে করে:

- ভুল হলে সাথে সাথে ধরা পড়ে (ডেবিট-ক্রেডিট না মিললে সিস্টেম সেভই করবে না)
- Trial Balance, Balance Sheet, P&L — এই তিনটা রিপোর্ট অটোমেটিক এবং সবসময় নির্ভুল থাকে
- এটাই বিশ্বব্যাপী স্বীকৃত অ্যাকাউন্টিং স্ট্যান্ডার্ড (GAAP-এর ভিত্তি)

### মূলনীতি (মনে রাখার সহজ নিয়ম)

| অ্যাকাউন্ট টাইপ | বাড়লে | কমলে |
|---|---|---|
| Asset (Cash, Bank, Inventory, Receivable) | Debit | Credit |
| Liability (Payable) | Credit | Debit |
| Equity | Credit | Debit |
| Income (Sales) | Credit | Debit |
| Expense (COGS, Rent, Salary...) | Debit | Credit |

---

## ২. Chart of Accounts (হিসাবের তালিকা)

প্রতিটা লেনদেন এই তালিকার অ্যাকাউন্টগুলোর মধ্যে ডেবিট-ক্রেডিট হয়ে পোস্ট হবে।

```
1000  Assets
  1010  Cash in Hand
  1020  Bank Account (একাধিক হতে পারে, প্রতিটার আলাদা কোড: 1021, 1022...)
  1030  Accounts Receivable (কাস্টমার বাকি)
  1040  Inventory (মজুদ পণ্য)

2000  Liabilities
  2010  Accounts Payable (সাপ্লায়ার বাকি)

3000  Equity
  3010  Owner's Equity
  3020  Retained Earnings

4000  Income
  4010  Sales Revenue
  4020  Sales Discount (contra-income)

5000  Expenses
  5010  Cost of Goods Sold (COGS)
  5020  Rent Expense
  5030  Salary Expense
  5040  Utility Expense
  5050  Transport Expense
  5060  Other Expense
```

এই তালিকা `accounts` টেবিলে সিড (seed) করে রাখা হবে; ইউজার চাইলে নতুন খরচের খাত যোগ করতে পারবে (৫০০০ সিরিজে)।

---

## ৩. ডেটাবেস স্কিমা (Migrations)

### 3.1 `accounts` (Chart of Accounts + Cash/Bank)

```php
Schema::create('accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();          // 1010, 1021, 5020...
    $table->string('name');                     // Cash in Hand, Dutch-Bangla Bank...
    $table->enum('type', ['asset','liability','equity','income','expense']);
    $table->enum('subtype', ['cash','bank','receivable','payable','inventory','other'])->nullable();
    $table->decimal('opening_balance', 15, 2)->default(0);
    $table->date('opening_date')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 3.2 `journal_entries` + `journal_entry_lines` (হিসাবের মূল ভিত্তি)

```php
Schema::create('journal_entries', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('reference_type');    // 'Sale','Purchase','Expense','Payment','Transfer'
    $table->unsignedBigInteger('reference_id');
    $table->string('description')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

Schema::create('journal_entry_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
    $table->foreignId('account_id')->constrained('accounts');
    $table->decimal('debit', 15, 2)->default(0);
    $table->decimal('credit', 15, 2)->default(0);
    $table->timestamps();
});
```

> **নিয়ম:** একটা `journal_entries` রেকর্ডের নিচে যতগুলো `journal_entry_lines` থাকবে, সবগুলোর debit-এর যোগফল = credit-এর যোগফল। এটা `LedgerService`-এ ভ্যালিডেট হবে (নিচে দেখুন)।

### 3.3 পণ্য ও মজুদ

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('sku')->unique()->nullable();
    $table->string('unit')->default('pcs');
    $table->decimal('cost_price', 15, 2)->default(0);   // weighted-average cost রাখাই ভালো
    $table->decimal('sale_price', 15, 2)->default(0);
    $table->integer('opening_qty')->default(0);
    $table->integer('reorder_level')->default(5);
    $table->timestamps();
});

Schema::create('stock_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->enum('type', ['in','out','adjustment']);
    $table->integer('qty');
    $table->decimal('unit_cost', 15, 2)->nullable();     // 'in' movement-এ থাকে
    $table->string('reference_type');                     // 'Sale','Purchase','Adjustment'
    $table->unsignedBigInteger('reference_id');
    $table->date('date');
    $table->timestamps();
});
```

> স্টক সবসময় `stock_movements` থেকে হিসাব হবে (SUM of in − SUM of out + opening_qty), `products.qty` কলাম রাখবেন না — নাহলে দুই জায়গায় সংখ্যা রাখলে গরমিল হতে পারে। প্রয়োজনে perfomance-এর জন্য একটা cached `current_stock` কলাম রাখতে পারেন যেটা প্রতি movement-এ আপডেট হয়, কিন্তু সোর্স অফ ট্রুথ থাকবে movements টেবিল।

### 3.4 কাস্টমার ও সাপ্লায়ার

```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('phone')->nullable();
    $table->string('address')->nullable();
    $table->decimal('opening_balance', 15, 2)->default(0); // (+) মানে বাকি পাবেন
    $table->timestamps();
});

Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('phone')->nullable();
    $table->string('address')->nullable();
    $table->decimal('opening_balance', 15, 2)->default(0); // (+) মানে বাকি দিবেন
    $table->timestamps();
});
```

### 3.5 বিক্রয় (Sales)

```php
Schema::create('sales', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_no')->unique();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->date('date');
    $table->decimal('subtotal', 15, 2);
    $table->decimal('discount', 15, 2)->default(0);
    $table->decimal('total', 15, 2);
    $table->decimal('paid_amount', 15, 2)->default(0);
    $table->foreignId('paid_to_account_id')->nullable()->constrained('accounts');
    $table->enum('status', ['paid','partial','due'])->default('due');
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

Schema::create('sale_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->integer('qty');
    $table->decimal('price', 15, 2);
    $table->decimal('cost_price', 15, 2);   // বিক্রয়ের সময়কার cost — COGS হিসাবের জন্য জরুরি
    $table->decimal('total', 15, 2);
});
```

### 3.6 ক্রয় (Purchases)

```php
Schema::create('purchases', function (Blueprint $table) {
    $table->id();
    $table->string('bill_no')->unique();
    $table->foreignId('supplier_id')->nullable()->constrained();
    $table->date('date');
    $table->decimal('total', 15, 2);
    $table->decimal('paid_amount', 15, 2)->default(0);
    $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts');
    $table->enum('status', ['paid','partial','due'])->default('due');
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

Schema::create('purchase_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->integer('qty');
    $table->decimal('cost', 15, 2);
    $table->decimal('total', 15, 2);
});
```

### 3.7 খরচ ও পেমেন্ট

```php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->foreignId('expense_account_id')->constrained('accounts'); // 5020, 5030...
    $table->foreignId('paid_from_account_id')->constrained('accounts'); // cash/bank
    $table->decimal('amount', 15, 2);
    $table->string('note')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->enum('type', ['receive','pay']);      // receive = কাস্টমার থেকে, pay = সাপ্লায়ারকে
    $table->string('party_type');                  // Customer / Supplier
    $table->unsignedBigInteger('party_id');
    $table->foreignId('account_id')->constrained('accounts');
    $table->decimal('amount', 15, 2);
    $table->string('note')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

Schema::create('transfers', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->foreignId('from_account_id')->constrained('accounts');
    $table->foreignId('to_account_id')->constrained('accounts');
    $table->decimal('amount', 15, 2);
    $table->string('note')->nullable();
    $table->timestamps();
});
```

### 3.8 ইউজার ও রোল

Laravel Breeze দিয়ে `users` টেবিল আসবে, তার সাথে `spatie/laravel-permission` প্যাকেজ দিয়ে রোল যোগ করুন (`admin`, `staff`, `accountant`)।

---

## ৪. মূল ইঞ্জিন — `LedgerService`

এইটাই পুরো সিস্টেমের হৃদয়। **কোনো কন্ট্রোলার সরাসরি ব্যালেন্স হিসাব করবে না** — সব লেনদেন এই সার্ভিসের মধ্য দিয়ে জার্নাল এন্ট্রি হিসেবে পোস্ট হবে।

```php
namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * @param array $lines  [['account_id'=>1, 'debit'=>100, 'credit'=>0], ...]
     */
    public function post(string $date, string $referenceType, int $referenceId, string $description, array $lines): JournalEntry
    {
        $totalDebit = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('credit');

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \RuntimeException("Journal entry not balanced: Debit {$totalDebit} != Credit {$totalCredit}");
        }

        return DB::transaction(function () use ($date, $referenceType, $referenceId, $description, $lines) {
            $entry = JournalEntry::create([
                'date' => $date,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            return $entry;
        });
    }

    public function accountBalance(int $accountId, ?string 	asOfDate = null): float
    {
        $query = JournalEntryLine::where('account_id', $accountId)
            ->when($asOfDate, fn($q) => $q->whereHas('journalEntry', fn($j) => $j->where('date', '<=', $asOfDate)));

        $debit = (clone $query)->sum('debit');
        $credit = (clone $query)->sum('credit');
        $opening = \App\Models\Account::find($accountId)->opening_balance;

        // Asset/Expense: debit বাড়ায়; Liability/Equity/Income: credit বাড়ায়
        $account = \App\Models\Account::find($accountId);
        return in_array($account->type, ['asset','expense'])
            ? $opening + $debit - $credit
            : $opening + $credit - $debit;
    }
}
```

### প্রতিটা মডিউল কীভাবে জার্নাল পোস্ট করবে

| লেনদেন | Debit | Credit |
|---|---|---|
| **বিক্রয় (নগদ)** | Cash/Bank | Sales Revenue |
| **বিক্রয় (বাকি)** | Accounts Receivable | Sales Revenue |
| — একই সাথে (COGS) | COGS | Inventory |
| **ক্রয় (নগদ)** | Inventory | Cash/Bank |
| **ক্রয় (বাকি)** | Inventory | Accounts Payable |
| **খরচ** | Expense Account | Cash/Bank |
| **কাস্টমার পেমেন্ট গ্রহণ** | Cash/Bank | Accounts Receivable |
| **সাপ্লায়ার পেমেন্ট প্রদান** | Accounts Payable | Cash/Bank |
| **অ্যাকাউন্ট ট্রান্সফার** | To Account | From Account |

**উদাহরণ — `SaleService::create()`:**

```php
public function create(array $data): Sale
{
    return DB::transaction(function () use ($data) {
        $sale = Sale::create([...]); // header

        $subtotal = 0; $totalCost = 0;
        foreach ($data['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $lineTotal = $item['qty'] * $item['price'];
            $subtotal += $lineTotal;
            $totalCost += $item['qty'] * $product->cost_price;

            $sale->items()->create([
                'product_id' => $product->id,
                'qty' => $item['qty'],
                'price' => $item['price'],
                'cost_price' => $product->cost_price,
                'total' => $lineTotal,
            ]);

            StockMovement::create([
                'product_id' => $product->id, 'type' => 'out', 'qty' => $item['qty'],
                'reference_type' => 'Sale', 'reference_id' => $sale->id, 'date' => $sale->date,
            ]);
        }

        $sale->update(['subtotal' => $subtotal, 'total' => $subtotal - $data['discount']]);

        $receivableAccount = Account::where('code', '1030')->first();
        $revenueAccount = Account::where('code', '4010')->first();
        $cogsAccount = Account::where('code', '5010')->first();
        $inventoryAccount = Account::where('code', '1040')->first();

        $lines = [];
        if ($data['paid_amount'] > 0) {
            $lines[] = ['account_id' => $data['paid_to_account_id'], 'debit' => $data['paid_amount'], 'credit' => 0];
        }
        $due = $sale->total - $data['paid_amount'];
        if ($due > 0) {
            $lines[] = ['account_id' => $receivableAccount->id, 'debit' => $due, 'credit' => 0];
        }
        $lines[] = ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $sale->total];

        app(LedgerService::class)->post($sale->date, 'Sale', $sale->id, "Sale #{$sale->invoice_no}", $lines);

        // COGS entry (আলাদা জার্নাল, একই sale-এর সাথে যুক্ত)
        app(LedgerService::class)->post($sale->date, 'Sale', $sale->id, "COGS #{$sale->invoice_no}", [
            ['account_id' => $cogsAccount->id, 'debit' => $totalCost, 'credit' => 0],
            ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $totalCost],
        ]);

        return $sale;
    });
}
```

`PurchaseService`, `ExpenseService`, `PaymentService`, `TransferService` একই প্যাটার্নে লিখবেন — উপরের টেবিল অনুযায়ী ডেবিট-ক্রেডিট বসিয়ে।

---

## ৫. ফিচার-ভিত্তিক মডিউল তালিকা

| মডিউল | রুট/কন্ট্রোলার | মূল কাজ |
|---|---|---|
| **Dashboard** | `DashboardController` | আজকের বিক্রয়/ক্রয়, ক্যাশ+ব্যাংক ব্যালেন্স (LedgerService থেকে), বাকি সামারি |
| **Sales** | `SaleController` + `SaleService` | ইনভয়েস তৈরি, তালিকা, PDF প্রিন্ট, ফেরত (return) |
| **Purchases** | `PurchaseController` + `PurchaseService` | বিল এন্ট্রি, তালিকা, PDF প্রিন্ট |
| **Products** | `ProductController` | পণ্য CRUD, স্টক রিপোর্ট (`stock_movements` থেকে হিসাব) |
| **Expenses** | `ExpenseController` + `ExpenseService` | খরচ এন্ট্রি, খাত অনুযায়ী রিপোর্ট |
| **Accounts (Cash/Bank)** | `AccountController` + `TransferService` | অ্যাকাউন্ট CRUD, ট্রান্সফার, লেজার ভিউ |
| **Customers / Suppliers** | `CustomerController`, `SupplierController` + `PaymentService` | CRUD, পেমেন্ট নেওয়া/দেওয়া, স্টেটমেন্ট (ledger) |
| **Reports** | `ReportController` | Trial Balance, P&L, Balance Sheet, Day Book, Stock Report, AR/AP Aging |
| **Users & Roles** | Breeze + Spatie Permission | admin / accountant / staff — পারমিশন গেট |

---

## ৬. রিপোর্ট কীভাবে বানাবেন (সত্যিকারের অ্যাকাউন্টিং রিপোর্ট)

যেহেতু সব কিছু `journal_entry_lines`-এ পোস্ট হচ্ছে, রিপোর্টগুলো এখন **কুয়েরি মাত্র**, আলাদা করে হিসাব রাখতে হয় না:

- **Trial Balance:** প্রতিটা account-এর debit ও credit যোগফল পাশাপাশি — টোটাল ডেবিট = টোটাল ক্রেডিট হতেই হবে, না হলে বাগ আছে।
- **Profit & Loss:** সব `income` টাইপ account-এর credit-balance যোগ − সব `expense` টাইপ account-এর debit-balance যোগ, একটা তারিখ-রেঞ্জে।
- **Balance Sheet:** Assets = Liabilities + Equity, একটা নির্দিষ্ট তারিখ পর্যন্ত সব account-এর ব্যালেন্স।
- **Cash Flow / Day Book:** `1010`, `1020...` (cash/bank) account-গুলোর journal lines তারিখ অনুযায়ী।
- **AR/AP Aging:** `sales`/`purchases`-এ `status != paid` গুলো due amount ও কতদিন পার হয়েছে হিসাব করে।

```php
// Trial Balance উদাহরণ
public function trialBalance($asOf)
{
    return Account::all()->map(function ($acc) use ($asOf) {
        $balance = app(LedgerService::class)->accountBalance($acc->id, $asOf);
        return [
            'code' => $acc->code, 'name' => $acc->name, 'type' => $acc->type,
            'debit' => in_array($acc->type, ['asset','expense']) ? max($balance, 0) : 0,
            'credit' => in_array($acc->type, ['liability','equity','income']) ? max($balance, 0) : 0,
        ];
    });
}
```

---

## ৭. সুপারিশকৃত প্যাকেজ

```bash
composer require laravel/breeze --dev        # অথেন্টিকেশন
composer require spatie/laravel-permission   # রোল/পারমিশন (admin/staff)
composer require barryvdh/laravel-dompdf     # ইনভয়েস/বিল PDF
composer require maatwebsite/excel           # রিপোর্ট এক্সপোর্ট (ঐচ্ছিক)
composer require livewire/livewire           # ইন্টারেক্টিভ ফর্ম (রিলোড ছাড়া রো যোগ করা ইত্যাদি) — ঐচ্ছিক কিন্তু সুবিধাজনক
```

Frontend-এর জন্য Blade + Livewire ব্যবহার করলে React/Vue ছাড়াই ইন্টারেক্টিভ ফর্ম বানাতে পারবেন (যেমন বিক্রয় ফর্মে একাধিক পণ্যের row)।

---

## ৮. ফোল্ডার স্ট্রাকচার

```
app/
  Models/
    Account.php, JournalEntry.php, JournalEntryLine.php
    Product.php, StockMovement.php
    Customer.php, Supplier.php
    Sale.php, SaleItem.php, Purchase.php, PurchaseItem.php
    Expense.php, Payment.php, Transfer.php
  Services/
    LedgerService.php
    SaleService.php, PurchaseService.php
    ExpenseService.php, PaymentService.php, TransferService.php
    ReportService.php
  Http/
    Controllers/  (থিন — শুধু ভ্যালিডেশন + সার্ভিস কল)
    Requests/     (StoreSaleRequest, StorePurchaseRequest...)
  Policies/       (SalePolicy, ExpensePolicy... — কে ডিলিট করতে পারবে)
resources/views/
  dashboard.blade.php
  sales/ (index, create, show/print)
  purchases/, products/, expenses/, accounts/, customers/, suppliers/, reports/
database/
  migrations/
  seeders/ (ChartOfAccountsSeeder.php — উপরের COA টেবিলটা এখানে সিড করবেন)
```

---

## ৯. ধাপে ধাপে বিল্ড রোডম্যাপ

1. **প্রজেক্ট সেটআপ:** `laravel new shop-accounts`, Breeze ইনস্টল, `.env`-এ ডাটাবেস কনফিগ, `php artisan migrate`
2. **Chart of Accounts:** migration + seeder লিখে ২ নং সেকশনের তালিকা সিড করুন
3. **LedgerService:** ৪ নং সেকশনের কোড বসান, ইউনিট টেস্ট লিখুন (debit≠credit হলে exception ছোড়ে কিনা)
4. **Products + Stock:** CRUD, `stock_movements` থেকে current stock বের করার হেল্পার (`Product::currentStock()`)
5. **Customers/Suppliers:** সাধারণ CRUD, opening_balance ফিল্ড সহ
6. **Sales মডিউল:** ফর্ম (একাধিক পণ্যের row — Livewire দিয়ে সহজ হবে) → `SaleService::create()` → PDF ইনভয়েস (dompdf)
7. **Purchases মডিউল:** Sales-এর মতোই, উল্টো দিক
8. **Expenses + Payments + Transfers:** ছোট ফর্ম, প্রতিটা `LedgerService::post()` কল করবে
9. **Reports:** Trial Balance → Balance Sheet → P&L → Day Book → AR/AP Aging, এই ক্রমে বানান (Trial Balance আগে বানালে বাকি রিপোর্ট যাচাই সহজ হয়)
10. **Roles/Permissions:** Spatie বসান, `admin` সব করতে পারবে, `staff` শুধু এন্ট্রি দিতে পারবে (ডিলিট/রিপোর্ট নিষেধ, Policy দিয়ে)
11. **টেস্টিং:** প্রতিটা Service-এর জন্য Feature Test — একটা বিক্রয় করে assert করুন Trial Balance এখনো balanced আছে কিনা

---

## ১০. গুরুত্বপূর্ণ ভালো অভ্যাস

- **সব লেনদেন `DB::transaction()`-এর ভেতরে** — মাঝপথে এরর হলে যেন আধা-সম্পূর্ণ ডেটা সেভ না হয়
- **কখনো balance/qty সরাসরি এডিট করবেন না** — সব সময় নতুন movement/journal entry দিয়ে সমন্বয় (adjustment) করুন, এতে audit trail অক্ষত থাকে
- **ডিলিট নয়, রিভার্সাল** — একটা বিক্রয় বাতিল করতে হলে বিপরীত জার্নাল এন্ট্রি পোস্ট করুন (reversal entry), রেকর্ড হার্ড-ডিলিট না করাই ভালো অ্যাকাউন্টিং প্র্যাকটিস
- **Form Request দিয়ে ভ্যালিডেশন**, কন্ট্রোলারে নয়
- **প্রতি রিলিজে Trial Balance টেস্ট চালান** — এইটা আপনার "সিস্টেম ঠিক আছে কিনা" এর সবচেয়ে ভালো পরীক্ষা

---

## ১১. এরপর কী?

এই ডকুমেন্ট অনুযায়ী চাইলে আমি এখন লিখে দিতে পারিঃ

- সম্পূর্ণ migrations + seeder ফাইল (কপি-পেস্ট রেডি)
- Models + relationships
- Services (SaleService, PurchaseService, ExpenseService, PaymentService, TransferService, ReportService) সম্পূর্ণ কোড
- Blade views (ফর্ম, তালিকা, প্রিন্ট)

কোনটা দিয়ে শুরু করতে চান বলুন — একসাথে সব দিলে অনেক বড় হয়ে যাবে, তাই ধাপে ধাপে করাই ভালো হবে।
