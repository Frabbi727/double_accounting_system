<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\SupplierService;
use Modules\Finance\Services\ExpenseService;
use Modules\Finance\Services\PaymentService;
use Modules\Finance\Services\TransferService;
use Tests\TestCase;

/**
 * Expense, Payment and Transfer — small ledger-only transactions that must
 * keep the books balanced.
 */
class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function balance(string $code): float
    {
        return $this->ledger()->balance(Account::where('code', $code)->first());
    }

    private function openCash(float $amount): void
    {
        // Open the already-seeded cash account (1010) — Debit 1010 / Credit 3010.
        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash,
            amount: $amount,
            date: config('shop.cutoff_date'),
            source: $cash,
        );
    }

    public function test_expense_reduces_cash_and_records_expense(): void
    {
        $this->openCash(25000);

        app(ExpenseService::class)->create([
            'expense_code' => '5020',   // shop rent
            'amount' => 3000,
            'date' => '2026-08-06',
        ]);

        $this->assertEqualsWithDelta(3000, $this->balance('5020'), 0.01);
        $this->assertEqualsWithDelta(22000, $this->balance('1010'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_expense_against_non_expense_account_is_rejected(): void
    {
        $this->openCash(25000);

        $this->expectException(\InvalidArgumentException::class);

        app(ExpenseService::class)->create([
            'expense_code' => '1010',   // cash is not an expense account
            'amount' => 1000,
        ]);
    }

    public function test_payment_received_reduces_receivable(): void
    {
        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        app(PaymentService::class)->receiveFromCustomer($customer, [
            'amount' => 2000,
            'date' => '2026-08-06',
        ]);

        $this->assertEqualsWithDelta(3000, $this->balance('1030'), 0.01);   // AR down
        $this->assertEqualsWithDelta(2000, $this->balance('1010'), 0.01);   // cash up
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_payment_made_reduces_payable(): void
    {
        $supplier = app(SupplierService::class)->create([
            'name' => 'ABC',
            'opening_items' => [['amount' => 4000, 'original_date' => '2026-06-10']],
        ]);
        $this->openCash(25000);

        app(PaymentService::class)->payToSupplier($supplier, [
            'amount' => 1500,
            'date' => '2026-08-06',
        ]);

        $this->assertEqualsWithDelta(2500, $this->balance('2010'), 0.01);   // AP down
        $this->assertEqualsWithDelta(23500, $this->balance('1010'), 0.01);  // cash down
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_receipt_exceeding_customer_due_is_rejected(): void
    {
        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        // Owes 5000 — trying to receive 6000 must fail (would create an advance).
        app(PaymentService::class)->receiveFromCustomer($customer, [
            'amount' => 6000, 'date' => '2026-08-06',
        ]);
    }

    public function test_payment_exceeding_supplier_due_is_rejected(): void
    {
        $supplier = app(SupplierService::class)->create([
            'name' => 'ABC',
            'opening_items' => [['amount' => 4000, 'original_date' => '2026-06-10']],
        ]);
        $this->openCash(25000);

        $this->expectException(\InvalidArgumentException::class);

        app(PaymentService::class)->payToSupplier($supplier, [
            'amount' => 4500, 'date' => '2026-08-06',
        ]);
    }

    public function test_partial_payments_accumulate_and_cannot_overshoot_the_remainder(): void
    {
        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);
        $payments = app(PaymentService::class);

        // Two partial receipts leave 5000 − 2000 − 1500 = 1500 due.
        $payments->receiveFromCustomer($customer, ['amount' => 2000, 'date' => '2026-08-06']);
        $payments->receiveFromCustomer($customer, ['amount' => 1500, 'date' => '2026-08-07']);
        $this->assertEqualsWithDelta(1500, $this->balance('1030'), 0.01);

        // The next receipt is capped at the 1500 remainder, not the original 5000.
        $this->expectException(\InvalidArgumentException::class);
        $payments->receiveFromCustomer($customer, ['amount' => 2000, 'date' => '2026-08-08']);
    }

    public function test_transfer_moves_money_between_accounts(): void
    {
        $this->openCash(25000);
        $bank = Account::where('code', '1021')->first();   // seeded bank account

        app(TransferService::class)->transfer(
            from: Account::where('code', '1010')->first(),
            to: $bank,
            data: ['amount' => 10000, 'date' => '2026-08-06'],
        );

        $this->assertEqualsWithDelta(15000, $this->balance('1010'), 0.01);
        $this->assertEqualsWithDelta(10000, $this->balance('1021'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_transfer_to_same_account_is_rejected(): void
    {
        $cash = Account::where('code', '1010')->first();

        $this->expectException(\InvalidArgumentException::class);

        app(TransferService::class)->transfer(
            from: $cash,
            to: $cash,
            data: ['amount' => 1000],
        );
    }

    // ------------------------------------------------------------------
    // Insufficient-balance guard: you cannot spend money an account lacks.
    // ------------------------------------------------------------------

    public function test_supplier_payment_exceeding_account_balance_is_rejected(): void
    {
        $supplier = app(SupplierService::class)->create([
            'name' => 'ABC',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-06-10']],
        ]);
        $this->openCash(1000);   // only 1000 in hand

        $this->expectException(\InvalidArgumentException::class);

        // Well within the 5000 due, but the cash account only holds 1000.
        app(PaymentService::class)->payToSupplier($supplier, [
            'amount' => 2000, 'date' => '2026-08-06',
        ]);
    }

    public function test_supplier_payment_within_account_balance_succeeds(): void
    {
        $supplier = app(SupplierService::class)->create([
            'name' => 'ABC',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-06-10']],
        ]);
        $this->openCash(3000);

        app(PaymentService::class)->payToSupplier($supplier, [
            'amount' => 2000, 'date' => '2026-08-06',
        ]);

        $this->assertEqualsWithDelta(1000, $this->balance('1010'), 0.01);   // cash down
        $this->assertEqualsWithDelta(3000, $this->balance('2010'), 0.01);   // AP down
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_expense_exceeding_account_balance_is_rejected(): void
    {
        $this->openCash(1000);

        $this->expectException(\InvalidArgumentException::class);

        app(ExpenseService::class)->create([
            'expense_code' => '5020',   // shop rent
            'amount' => 3000,
            'date' => '2026-08-06',
        ]);
    }

    public function test_transfer_exceeding_source_balance_is_rejected(): void
    {
        $this->openCash(1000);

        $this->expectException(\InvalidArgumentException::class);

        app(TransferService::class)->transfer(
            from: Account::where('code', '1010')->first(),
            to: Account::where('code', '1021')->first(),
            data: ['amount' => 5000, 'date' => '2026-08-06'],
        );
    }

    public function test_transfer_from_loan_account_is_not_balance_guarded(): void
    {
        // A loan (liability) source has no spendable cap — drawing on it raises
        // the liability, so the guard must let it through even from zero.
        app(TransferService::class)->transfer(
            from: Account::where('code', '2020')->first(),   // Bank Loan
            to: Account::where('code', '1010')->first(),     // Cash
            data: ['amount' => 5000, 'date' => '2026-08-06'],
        );

        $this->assertEqualsWithDelta(5000, $this->balance('1010'), 0.01);   // cash up
        $this->assertEqualsWithDelta(5000, $this->balance('2020'), 0.01);   // loan up
        $this->ledger()->assertLedgerBalanced();
    }
}
