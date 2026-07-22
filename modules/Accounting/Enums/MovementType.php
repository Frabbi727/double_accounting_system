<?php

namespace Modules\Accounting\Enums;

enum MovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';

    /** Localized label (bn/en) resolved from lang/{locale}/accounting.php. */
    public function label(): string
    {
        return __('accounting.movement_type.'.$this->value);
    }
}
