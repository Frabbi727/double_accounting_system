<?php

namespace Modules\Accounting\Exceptions;

class PeriodLockedException extends AccountingException
{
    public static function forDate(string $date): self
    {
        return new self(__('accounting.errors.period_locked', ['date' => $date]));
    }
}
