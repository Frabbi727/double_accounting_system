<?php

namespace Modules\Finance\Services;

use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;
use App\Support\Money;
use Modules\Accounting\Services\Reporting\ReportService;

/**
 * Records cash/bank payments to and from parties.
 *
 * Received from a customer:  Debit Cash/Bank, Credit 1030 Receivable
 * Made to a supplier:        Debit 2010 Payable, Credit Cash/Bank
 *
 * The journal entry references the party (reference_type + reference_id) so
 * per-party statements can be built later without a separate payments table.
 */
class PaymentService
{
    private const CASH_CODE = '1010';

    private const RECEIVABLE_CODE = '1030';

    private const PAYABLE_CODE = '2010';

    private const EPSILON = 0.005;

    public function __construct(
        private LedgerService $ledger,
        private ReportService $reports,
        private AccountBalanceGuard $balanceGuard,
    ) {}

    /**
     * @param  array{amount:float, date?:string, payment_account_id?:int, notes?:string}  $data
     */
    public function receiveFromCustomer(Customer $customer, array $data): JournalEntry
    {
        $amount = $this->amount($data);
        $this->guardAgainstOverpayment('customer', $customer->id, $amount);
        $account = $this->paymentAccount($data);
        $date = $data['date'] ?? now()->toDateString();

        return $this->ledger->post(
            date: $date,
            referenceType: 'PaymentIn',
            referenceId: $customer->id,
            description: $data['notes'] ?? __('finance.payment_in_description', ['party' => $customer->name]),
            lines: [
                ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->account(self::RECEIVABLE_CODE)->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    /**
     * @param  array{amount:float, date?:string, payment_account_id?:int, notes?:string}  $data
     */
    public function payToSupplier(Supplier $supplier, array $data): JournalEntry
    {
        $amount = $this->amount($data);
        $this->guardAgainstOverpayment('supplier', $supplier->id, $amount);
        $account = $this->paymentAccount($data);
        $date = $data['date'] ?? now()->toDateString();
        $this->balanceGuard->assertSufficient($account, $amount, $date);

        return $this->ledger->post(
            date: $date,
            referenceType: 'PaymentOut',
            referenceId: $supplier->id,
            description: $data['notes'] ?? __('finance.payment_out_description', ['party' => $supplier->name]),
            lines: [
                ['account_id' => $this->account(self::PAYABLE_CODE)->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $account->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    private function amount(array $data): float
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('finance.errors.amount_positive'));
        }

        return $amount;
    }

    /**
     * A receipt/payment can never exceed what the party currently owes (or is
     * owed). Partial payments are fine; overpayment — which would flip the
     * control account into an advance balance — is rejected.
     *
     * @param  'customer'|'supplier'  $party
     */
    private function guardAgainstOverpayment(string $party, int $id, float $amount): void
    {
        $due = $this->reports->partyDue($party, $id);

        if ($amount > $due + self::EPSILON) {
            throw new \InvalidArgumentException(__('finance.errors.exceeds_due', [
                'due' => Money::taka(max($due, 0)),
            ]));
        }
    }

    private function paymentAccount(array $data): Account
    {
        if (! empty($data['payment_account_id'])) {
            return Account::findOrFail($data['payment_account_id']);
        }

        return $this->account(self::CASH_CODE);
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
