<?php

namespace Modules\Accounting\Exceptions;

class UnbalancedJournalException extends AccountingException
{
    public static function make(float $debit, float $credit): self
    {
        return new self(__('accounting.errors.unbalanced', [
            'debit' => number_format($debit, 2),
            'credit' => number_format($credit, 2),
            'diff' => number_format(abs($debit - $credit), 2),
        ]));
    }
}
