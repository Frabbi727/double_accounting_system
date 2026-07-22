<?php

namespace Modules\Accounting\Exceptions;

class OpeningAlreadyPostedException extends AccountingException
{
    public static function for(string $model, int $id): self
    {
        return new self(__('accounting.errors.opening_already_posted', [
            'model' => $model,
            'id' => $id,
        ]));
    }
}
