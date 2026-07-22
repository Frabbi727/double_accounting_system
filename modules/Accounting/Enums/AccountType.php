<?php

namespace Modules\Accounting\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    /**
     * Assets and Expenses increase on the DEBIT side.
     * Liabilities, Equity and Income increase on the CREDIT side.
     *
     * This single method is the only place in the codebase that encodes
     * the debit/credit rule. Everything else asks this.
     */
    public function increasesWithDebit(): bool
    {
        return match ($this) {
            self::Asset, self::Expense => true,
            default => false,
        };
    }

    /** Localized label (bn/en) resolved from lang/{locale}/accounting.php. */
    public function label(): string
    {
        return __('accounting.account_type.'.$this->value);
    }

    /** Appears on the Balance Sheet (vs Profit & Loss). */
    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }
}
