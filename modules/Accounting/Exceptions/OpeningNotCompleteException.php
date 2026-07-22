<?php

namespace Modules\Accounting\Exceptions;

class OpeningNotCompleteException extends AccountingException
{
    public static function make(): self
    {
        return new self(__('accounting.errors.opening_not_complete'));
    }
}
