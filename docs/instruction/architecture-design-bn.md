# আর্কিটেকচার ডিজাইন — দোকানের হিসাব সফটওয়্যার

> ব্লুপ্রিন্টে ছিল **কী থাকবে**। এই ডকুমেন্টে আছে **কোড কীভাবে সাজানো থাকবে, ডেটা কোন পথে যাবে, আর টেবিলগুলোর সম্পর্ক কী**।

---

## ভাগ ১ — সামগ্রিক আর্কিটেকচার (৫টা স্তর)

```
┌──────────────────────────────────────────────────────────────┐
│  ১. প্রেজেন্টেশন লেয়ার  (ইউজার যা দেখে)                        │
│     Blade ভিউ · Livewire কম্পোনেন্ট · PDF টেমপ্লেট             │
└──────────────────────────────────────────────────────────────┘
                              ↕
┌──────────────────────────────────────────────────────────────┐
│  ২. HTTP লেয়ার  (অনুরোধ গ্রহণ ও যাচাই)                        │
│     Route · Controller · FormRequest · Middleware · Policy    │
│     ⚠ এখানে কোনো হিসাব হবে না — শুধু যাচাই করে সার্ভিসে পাঠাবে   │
└──────────────────────────────────────────────────────────────┘
                              ↕
┌──────────────────────────────────────────────────────────────┐
│  ৩. সার্ভিস লেয়ার  (সব ব্যবসায়িক নিয়ম এখানে) ★ হৃদয় ★         │
│     SaleService · PurchaseService · LedgerService              │
│     InventoryService · DiscountService · RebateService         │
└──────────────────────────────────────────────────────────────┘
                              ↕
┌──────────────────────────────────────────────────────────────┐
│  ৪. ডোমেইন লেয়ার  (ডেটার গঠন ও সম্পর্ক)                       │
│     Eloquent Model · Relationship · Scope · Accessor           │
└──────────────────────────────────────────────────────────────┘
                              ↕
┌──────────────────────────────────────────────────────────────┐
│  ৫. ডেটা লেয়ার                                                │
│     MySQL · Migration · Seeder                                 │
└──────────────────────────────────────────────────────────────┘
```

### কেন এই ভাগ?

**সবচেয়ে বড় ভুল যেটা মানুষ করে:** কন্ট্রোলারে সব হিসাব লিখে ফেলা। এতে একই হিসাব ৫ জায়গায় লিখতে হয়, একটা পাল্টালে বাকিগুলো পাল্টাতে ভুলে যান, আর টেস্ট করা অসম্ভব হয়ে যায়।

**নিয়ম:** কন্ট্রোলার হবে **পাতলা** (thin), সার্ভিস হবে **মোটা** (fat)।

```php
// ❌ ভুল — কন্ট্রোলারে হিসাব
public function store(Request $request) {
    $sale = Sale::create([...]);
    foreach ($request->items as $item) {
        $product = Product::find($item['product_id']);
        $product->stock -= $item['qty'];      // স্টক এখানে কমাচ্ছে
        $product->save();
        // ... আরো ৫০ লাইন হিসাব
    }
}

// ✅ ঠিক — কন্ট্রোলার শুধু পাস করে
public function store(StoreSaleRequest $request, SaleService $service) {
    $sale = $service->create($request->validated());
    return redirect()->route('sales.show', $sale)->with('success', 'বিক্রয় সংরক্ষিত হয়েছে');
}
```

---

## ভাগ ২ — সার্ভিস লেয়ারের ভেতরের কাঠামো

এইটাই সবচেয়ে গুরুত্বপূর্ণ অংশ। সার্ভিসগুলো একটা নির্দিষ্ট শ্রেণিবিন্যাসে সাজানো থাকবে:

```
                    ┌─────────────────────┐
                    │   LedgerService     │  ← সবার নিচে, সবাই এটাকে ডাকে
                    │  (হিসাবের ইঞ্জিন)    │     এটা কাউকে ডাকে না
                    └─────────────────────┘
                              ↑
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
┌───────────────┐   ┌─────────────────┐   ┌────────────────┐
│InventoryService│   │  PricingService │   │ PartyService   │
│ (স্টক হিসাব)   │   │ (দাম ও ছাড়)     │   │(বাকির হিসাব)   │
└───────────────┘   └─────────────────┘   └────────────────┘
        ↑                     ↑                     ↑
        └─────────────────────┼─────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
┌───────────────┐   ┌─────────────────┐   ┌────────────────┐
│  SaleService  │   │ PurchaseService │   │ ExpenseService │
│               │   │                 │   │ PaymentService │
└───────────────┘   └─────────────────┘   └────────────────┘
                              ↑
                    ┌─────────────────────┐
                    │    Controllers      │
                    └─────────────────────┘
```

**নিয়ম:** তীর সবসময় **উপরের দিকে** যাবে। নিচের সার্ভিস কখনো উপরেরটাকে ডাকবে না। `LedgerService` কখনো `SaleService`-কে ডাকবে না — নাহলে circular dependency হবে।

### প্রতিটা সার্ভিসের দায়িত্ব

| সার্ভিস | একমাত্র দায়িত্ব | কী জানে না |
|---|---|---|
| **LedgerService** | জার্নাল এন্ট্রি পোস্ট করা, ব্যালেন্স বের করা | বিক্রয়/ক্রয় কী জিনিস, তা জানে না |
| **InventoryService** | স্টক বাড়ানো/কমানো, weighted-average cost হিসাব | টাকার হিসাব জানে না |
| **PricingService** | দাম নির্ধারণ, ডিসকাউন্ট প্রয়োগ | স্টক বা লেজার জানে না |
| **PartyService** | কাস্টমার/সাপ্লায়ারের বাকি হিসাব, ক্রেডিট লিমিট চেক | পণ্য জানে না |
| **SaleService** | একটা বিক্রয়ের পুরো প্রক্রিয়া সাজানো (orchestration) | নিজে হিসাব করে না, নিচের সার্ভিসদের ডাকে |
| **RebateService** | রিবেট/ইনসেন্টিভ অর্জন ট্র্যাক ও প্রয়োগ | — |
| **ReportService** | সব রিপোর্টের কুয়েরি | কোনো ডেটা লেখে না, শুধু পড়ে |

> **মূল ধারণা:** প্রতিটা সার্ভিস একটাই কাজ করে, ভালোভাবে করে। `SaleService` নিজে স্টক কমায় না — সে `InventoryService`-কে বলে "এই পণ্যের এতগুলো কমাও"।

---

## ভাগ ৩ — একটা বিক্রয়ের সম্পূর্ণ যাত্রাপথ

এইটা দেখলে পুরো আর্কিটেকচার পরিষ্কার হয়ে যাবে। ধরুন ইউজার একটা বিক্রয় সেভ করলেন:

```
১. ইউজার ফর্ম সাবমিট করল
   └→ POST /sales
        ↓
২. Route → SaleController@store
        ↓
৩. StoreSaleRequest (FormRequest)
   ├─ পণ্য আছে কিনা?
   ├─ পরিমাণ ০-এর বেশি কিনা?
   ├─ তারিখ ঠিক আছে কিনা?
   └─ ❌ ভুল হলে → ফর্মে এরর দেখিয়ে ফেরত
        ↓ ✅
৪. SalePolicy — এই ইউজারের বিক্রয় করার অনুমতি আছে?
        ↓ ✅
৫. SaleService::create()  ← এখান থেকে সব হিসাব শুরু
   │
   ├─ DB::transaction() শুরু  ⚠ এর ভেতরে যা হবে, সব একসাথে হবে
   │  │
   │  ├─ (ক) PartyService::checkCreditLimit()
   │  │      └→ কাস্টমারের ক্রেডিট লিমিট পার হচ্ছে? → হলে থামাও
   │  │
   │  ├─ (খ) InventoryService::checkAvailability()
   │  │      └→ স্টকে যথেষ্ট মাল আছে? → না থাকলে থামাও
   │  │
   │  ├─ (গ) PricingService::calculate()
   │  │      ├→ কাস্টমারের ডিফল্ট ডিসকাউন্ট বসাও
   │  │      ├→ লাইন ডিসকাউন্ট প্রয়োগ করো
   │  │      ├→ বিল ডিসকাউন্ট প্রয়োগ করো
   │  │      └→ সাব-টোটাল, মোট বের করো
   │  │
   │  ├─ (ঘ) Sale + SaleItem রেকর্ড তৈরি
   │  │      └→ প্রতিটা আইটেমে তখনকার cost_price সেভ করো ★
   │  │
   │  ├─ (ঙ) InventoryService::stockOut()
   │  │      ├→ stock_movements-এ 'out' রেকর্ড
   │  │      └→ COGS = Σ (পরিমাণ × তখনকার ক্রয়মূল্য)
   │  │
   │  ├─ (চ) LedgerService::post()  — জার্নাল ১ (বিক্রয়)
   │  │      ├→ Debit:  ক্যাশ/ব্যাংক (যত নগদ পেলেন)
   │  │      ├→ Debit:  কাস্টমার বাকি (যত বাকি রইল)
   │  │      ├→ Debit:  ডিসকাউন্ট (যত ছাড় দিলেন)
   │  │      └→ Credit: বিক্রয় আয় (মোট)
   │  │
   │  ├─ (ছ) LedgerService::post()  — জার্নাল ২ (COGS)
   │  │      ├→ Debit:  COGS খরচ
   │  │      └→ Credit: ইনভেন্টরি (স্টকের মূল্য কমল)
   │  │
   │  └─ (জ) Event: SaleCreated ছাড়ো
   │         ├→ Listener: রিবেট/ইনসেন্টিভ অর্জন আপডেট
   │         ├→ Listener: কম স্টক হলে অ্যালার্ট
   │         └→ Listener: অডিট লগ লেখো
   │
   └─ DB::commit()  ✅ সব ঠিক থাকলে একসাথে সেভ
      DB::rollback() ❌ কোথাও এরর হলে সব বাতিল
        ↓
৬. Controller → redirect + সফলতার বার্তা
        ↓
৭. ইউজার ইনভয়েস দেখে, প্রিন্ট করে
```

### এখানে ২টা জিনিস খেয়াল করুন

**১) `DB::transaction()` কেন জরুরি?**

ধরুন স্টক কমল কিন্তু জার্নাল এন্ট্রি পোস্ট হওয়ার আগে বিদ্যুৎ চলে গেল। তাহলে স্টক কমে গেছে কিন্তু টাকার হিসাব হয়নি — সিস্টেম গরমিল। ট্রানজেকশন ব্যবহার করলে হয় **সব হবে**, নয় **কিছুই হবে না**।

**২) `cost_price` কেন ইনভয়েসে সেভ করবেন?**

আজ সাবানের ক্রয়মূল্য ৳৪০, বিক্রি করলেন ৳৫৫-এ, লাভ ৳১৫। আগামী মাসে ক্রয়মূল্য ৳৫০ হলো। যদি cost_price সেভ না করে থাকেন, তাহলে পুরনো বিক্রয়ের লাভ এখন দেখাবে ৳৫ — যেটা **সম্পূর্ণ ভুল**। তাই বিক্রির মুহূর্তের ক্রয়মূল্য সেই ইনভয়েসেই আটকে রাখতে হবে।

---

## ভাগ ৪ — ডেটাবেস আর্কিটেকচার (ERD)

### ৪.১ সম্পর্কের মানচিত্র

```
┌──────────┐         ┌──────────────┐         ┌───────────────┐
│  users   │────────<│    sales     │>────────│   customers   │
└──────────┘         └──────────────┘         └───────────────┘
                            │ 1                        │
                            │                          │
                            ∨ N                        ∨ N
                     ┌──────────────┐          ┌───────────────┐
                     │  sale_items  │          │   payments    │
                     └──────────────┘          └───────────────┘
                            │ N                        │
                            │                          │
                            ∨ 1                        │
                     ┌──────────────┐                  │
                     │   products   │                  │
                     └──────────────┘                  │
                            │ 1                        │
                            ∨ N                        │
                  ┌──────────────────┐                 │
                  │ stock_movements  │                 │
                  └──────────────────┘                 │
                                                       │
        ┌──────────────────────────────────────────────┘
        │
        ∨
┌────────────────────┐  1     N  ┌──────────────────────┐
│  journal_entries   │──────────<│ journal_entry_lines  │
└────────────────────┘           └──────────────────────┘
                                            │ N
                                            ∨ 1
                                    ┌──────────────┐
                                    │   accounts   │
                                    └──────────────┘
                                    (Chart of Accounts)
```

### ৪.২ টেবিলগুলোর শ্রেণিবিভাগ

| শ্রেণি | টেবিল | বৈশিষ্ট্য |
|---|---|---|
| **মাস্টার** | products, customers, suppliers, accounts, users, expense_categories | কম পরিবর্তন হয়, রেফারেন্স হিসেবে ব্যবহৃত |
| **লেনদেন হেডার** | sales, purchases, expenses, payments, transfers, returns | প্রতিদিন নতুন রেকর্ড |
| **লেনদেন লাইন** | sale_items, purchase_items | হেডারের সাথে cascade delete |
| **লেজার (অপরিবর্তনীয়)** | journal_entries, journal_entry_lines | ★ কখনো UPDATE/DELETE হবে না |
| **মুভমেন্ট (অপরিবর্তনীয়)** | stock_movements | ★ কখনো UPDATE/DELETE হবে না |
| **স্কিম** | discount_rules, incentive_schemes, rebate_agreements | ছাড়/বোনাসের নিয়ম |
| **অডিট** | activity_logs | কে কখন কী করল |

### ৪.৩ অপরিবর্তনীয় (Immutable) টেবিলের নিয়ম

এইটা পেশাদার অ্যাকাউন্টিং সফটওয়্যারের সবচেয়ে গুরুত্বপূর্ণ নীতি:

> **`journal_entry_lines` আর `stock_movements` — এই দুই টেবিলে একবার রেকর্ড ঢুকলে আর কখনো এডিট বা ডিলিট হবে না।**

ভুল হলে কী করবেন? **রিভার্সাল এন্ট্রি** দিবেন — উল্টো একটা এন্ট্রি যা আগেরটাকে বাতিল করে।

```
মূল এন্ট্রি:        Debit ক্যাশ ৫০০ / Credit বিক্রয় ৫০০
রিভার্সাল এন্ট্রি:   Debit বিক্রয় ৫০০ / Credit ক্যাশ ৫০০
                    ─────────────────────────────────────
নীট প্রভাব:                    ০ (বাতিল হয়ে গেল)
```

**কেন এটা ভালো?** কারণ ইতিহাস অক্ষত থাকে। ৬ মাস পর প্রশ্ন উঠলে "এই ৳৫০০ কোথায় গেল?" — আপনি দেখাতে পারবেন কখন এন্ট্রি হয়েছিল, কে করেছিল, কখন বাতিল হয়েছিল, কে বাতিল করেছিল।

### ৪.৪ ডেরাইভড ডেটা — কখনো সংরক্ষণ করবেন না

| যা জানতে চান | ❌ ভুল উপায় | ✅ সঠিক উপায় |
|---|---|---|
| পণ্যের বর্তমান স্টক | `products.stock` কলাম রাখা | `stock_movements` থেকে SUM |
| ক্যাশ ব্যালেন্স | `accounts.balance` কলাম | `journal_entry_lines` থেকে SUM |
| কাস্টমারের বাকি | `customers.due` কলাম | ওপেনিং + বিক্রয় − পেমেন্ট |

**কেন?** দুই জায়গায় একই তথ্য রাখলে একদিন না একদিন সেগুলো আলাদা হয়ে যাবেই — তখন কোনটা সত্যি বোঝা যাবে না।

**পারফরম্যান্সের জন্য কী করবেন?** যদি ডেটা অনেক বেড়ে যায়, তখন **snapshot** টেবিল রাখুন — মাসের শেষে প্রতিটা অ্যাকাউন্টের ক্লোজিং ব্যালেন্স সেভ করে রাখুন। তখন হিসাব হবে "গত মাসের ক্লোজিং + এই মাসের এন্ট্রি" — অনেক দ্রুত, অথচ সোর্স অফ ট্রুথ এখনো লেজারই থাকল।

---

## ভাগ ৫ — ফোল্ডার স্ট্রাকচার

```
app/
├── Models/
│   ├── Account.php              JournalEntry.php      JournalEntryLine.php
│   ├── Product.php              StockMovement.php     ProductCategory.php
│   ├── Customer.php             Supplier.php
│   ├── Sale.php                 SaleItem.php          SaleReturn.php
│   ├── Purchase.php             PurchaseItem.php      PurchaseReturn.php
│   ├── Expense.php              Payment.php           Transfer.php
│   ├── DiscountRule.php         IncentiveScheme.php   RebateAgreement.php
│   └── User.php
│
├── Services/
│   ├── Accounting/
│   │   ├── LedgerService.php         ← জার্নাল পোস্টিং ইঞ্জিন
│   │   ├── ChartOfAccountService.php
│   │   └── OpeningBalanceService.php ← ওপেনিং ব্যালেন্স সেটআপ
│   ├── Inventory/
│   │   ├── InventoryService.php      ← স্টক in/out
│   │   ├── CostingService.php        ← weighted-average হিসাব
│   │   └── StockAdjustmentService.php
│   ├── Transaction/
│   │   ├── SaleService.php           PurchaseService.php
│   │   ├── ExpenseService.php        PaymentService.php
│   │   ├── TransferService.php       ReturnService.php
│   ├── Pricing/
│   │   ├── PricingService.php        DiscountService.php
│   │   ├── IncentiveService.php      RebateService.php
│   └── Reporting/
│       ├── ReportService.php         TrialBalanceService.php
│       ├── ProfitLossService.php     BalanceSheetService.php
│       └── AgingService.php
│
├── Http/
│   ├── Controllers/          ← পাতলা, শুধু সার্ভিস কল
│   ├── Requests/             ← StoreSaleRequest, StorePurchaseRequest...
│   └── Middleware/
│
├── Policies/                 ← কে কী করতে পারবে
├── Events/                   ← SaleCreated, StockLow, PaymentReceived
├── Listeners/                ← UpdateRebateProgress, SendLowStockAlert
├── Enums/                    ← AccountType, SaleStatus, MovementType
└── Exceptions/               ← InsufficientStock, UnbalancedJournal, CreditLimitExceeded

database/
├── migrations/
└── seeders/
    ├── ChartOfAccountsSeeder.php   ← হিসাবের তালিকা
    ├── ExpenseCategorySeeder.php
    └── RolePermissionSeeder.php

resources/views/
├── layouts/                  ← app.blade.php, print.blade.php
├── sales/                    ← index, create, show, print
├── purchases/  products/  expenses/  accounts/
├── customers/  suppliers/    reports/
└── components/               ← রি-ইউজেবল Blade কম্পোনেন্ট

tests/
├── Unit/Services/            ← LedgerServiceTest, CostingServiceTest
└── Feature/                  ← SaleFlowTest, TrialBalanceTest
```

---

## ভাগ ৬ — Enum দিয়ে টাইপ নিরাপত্তা

স্ট্রিং হার্ডকোড করলে টাইপো হয় (`'recieve'` বনাম `'receive'`)। PHP 8-এর Enum ব্যবহার করুন:

```php
enum AccountType: string {
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    // ব্যবসায়িক নিয়ম Enum-এর ভেতরেই রাখুন
    public function increasesWithDebit(): bool {
        return in_array($this, [self::Asset, self::Expense]);
    }
}

enum MovementType: string {
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
}

enum SaleStatus: string {
    case Paid = 'paid';
    case Partial = 'partial';
    case Due = 'due';
}
```

এখন `$account->type->increasesWithDebit()` লিখলেই হবে — ডেবিট না ক্রেডিট, সেই জ্ঞান একজায়গায় থাকল।

---

## ভাগ ৭ — Event-Driven অংশ

কিছু কাজ মূল লেনদেনের **পরে** হবে, যাতে বিক্রয় সেভ হওয়া ধীর না হয়:

```php
// SaleService-এর শেষে
event(new SaleCreated($sale));
```

```
SaleCreated ইভেন্ট
    ├→ UpdateIncentiveProgress   (সেলসম্যানের টার্গেট আপডেট)
    ├→ UpdateRebateProgress      (সাপ্লায়ার রিবেট ট্র্যাকিং)
    ├→ CheckLowStock             (স্টক কম হলে অ্যালার্ট)
    ├→ LogActivity               (অডিট লগ)
    └→ SendInvoiceSms            (কাস্টমারকে SMS — queue-এ)
```

**সুবিধা:** পরে নতুন ফিচার যোগ করতে হলে শুধু নতুন Listener লিখবেন, `SaleService` ছুঁতে হবে না।

---

## ভাগ ৮ — নিরাপত্তা ও অনুমতি স্তর

```
১. Middleware        → লগইন করা আছে কিনা
2. Role              → admin / accountant / staff
3. Permission        → sales.create, reports.view, sales.delete
4. Policy            → এই নির্দিষ্ট রেকর্ডে এই ইউজারের অধিকার আছে কিনা
5. FormRequest       → ডেটা ঠিক আছে কিনা
6. Service Guard     → ব্যবসায়িক নিয়ম (ক্রেডিট লিমিট, স্টক)
```

**উদাহরণ — স্টাফ ক্রয়মূল্য দেখতে পাবে না:**

```php
// SaleItemResource বা Blade-এ
@can('viewCostPrice', $product)
    <td>{{ $product->cost_price }}</td>
@endcan
```

---

## ভাগ ৯ — টেস্টিং কৌশল

| স্তর | কী টেস্ট করবেন |
|---|---|
| **Unit** | `LedgerService` — ডেবিট≠ক্রেডিট হলে exception ছোড়ে? · `CostingService` — weighted-average ঠিক আসে? |
| **Feature** | পুরো বিক্রয় ফ্লো — স্টক কমল? জার্নাল পোস্ট হলো? বাকি বাড়ল? |
| **Invariant** ★ | প্রতিটা টেস্টের শেষে — **ট্রায়াল ব্যালেন্স এখনো মিলছে?** |

সবচেয়ে দামি টেস্ট এইটা:

```php
public function test_trial_balance_always_balances()
{
    // এলোমেলোভাবে ৫০টা লেনদেন করুন
    $this->createRandomTransactions(50);

    $debit = JournalEntryLine::sum('debit');
    $credit = JournalEntryLine::sum('credit');

    $this->assertEquals($debit, $credit, 'হিসাব মিলছে না!');
}
```

এই একটা টেস্ট পাস করলে বুঝবেন পুরো অ্যাকাউন্টিং ইঞ্জিন সুস্থ আছে।

---

## ভাগ ১০ — ডিপ্লয়মেন্ট আর্কিটেকচার

আপনার প্রয়োজন অনুযায়ী ৩টা বিকল্প:

| বিকল্প | কোথায় চলবে | কার জন্য |
|---|---|---|
| **অফলাইন (একক)** | দোকানের কম্পিউটারে XAMPP/Laragon | ১টা দোকান, ইন্টারনেট নেই |
| **লোকাল নেটওয়ার্ক** | দোকানের একটা পিসি সার্ভার, বাকিরা ব্রাউজারে | ৩-৫ জন একসাথে কাজ করে |
| **ক্লাউড** ★ সুপারিশ | VPS (DigitalOcean/Hetzner) + MySQL | কোথাও থেকে দেখতে চান, একাধিক দোকান |

**ক্লাউড সেটআপ:**
```
Nginx → PHP-FPM (Laravel) → MySQL
                ↓
        Redis (cache + queue)
                ↓
        Queue Worker (SMS, রিপোর্ট জেনারেশন)
                ↓
        দৈনিক অটো-ব্যাকআপ (spatie/laravel-backup)
```

---

## ভাগ ১১ — মূল সিদ্ধান্তগুলোর সারসংক্ষেপ

| সিদ্ধান্ত | কী বেছেছি | কেন |
|---|---|---|
| ব্যবসায়িক লজিক কোথায় | Service লেয়ারে | পুনঃব্যবহার ও টেস্টযোগ্য |
| হিসাবের ভিত্তি | Double-entry জার্নাল | সবসময় মিলবে, সব রিপোর্ট অটো |
| স্টকের সত্য | `stock_movements` টেবিল | অডিট ট্রেইল, গরমিল অসম্ভব |
| ক্রয়মূল্য পদ্ধতি | Weighted Average | সহজ ও নির্ভুল |
| ভুল সংশোধন | রিভার্সাল এন্ট্রি | ইতিহাস অক্ষত থাকে |
| লেনদেনের নিরাপত্তা | DB::transaction() | আধা-সেভ হবে না |
| পরবর্তী কাজ | Event + Listener | মূল ফ্লো দ্রুত থাকে |
| ডেরাইভড ডেটা | সংরক্ষণ করব না | দুই সত্য থাকবে না |

---

## পরের ধাপ

আর্কিটেকচার পরিষ্কার হলে এবার বাস্তব কোড। যেকোনো একটা দিয়ে শুরু করতে পারি:

1. **সব migration ফাইল** — ডেটাবেস দাঁড় করানো
2. **LedgerService + Model** — হিসাবের ইঞ্জিন (এইটা আগে করলে বাকি সব সহজ)
3. **ক্রয় মডিউল** — স্টক ঢোকানো
4. **বিক্রয় মডিউল** — ইনভয়েস সহ

কোনটা দিয়ে শুরু করব বলুন।
