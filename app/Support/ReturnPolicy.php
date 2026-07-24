<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Owner-configurable policy for how a sale return treats the original invoice
 * discount (requirement §2, "Discount Handling"). Stored in the settings table.
 *
 *   ignore       — refund at the original selling price; the invoice discount is
 *                  ignored (the shop absorbs the discount it originally gave).
 *   proportional — the refund is reduced by the returned line's proportional
 *                  share of the line + whole-bill discount, and that share is
 *                  reversed against 4020 Sales Discount.
 */
class ReturnPolicy
{
    public const KEY = 'returns.discount_policy';

    public const IGNORE = 'ignore';

    public const PROPORTIONAL = 'proportional';

    /** @return array<int, string> */
    public static function options(): array
    {
        return [self::IGNORE, self::PROPORTIONAL];
    }

    public static function discountPolicy(): string
    {
        $value = Setting::get(self::KEY, self::IGNORE);

        return in_array($value, self::options(), true) ? $value : self::IGNORE;
    }
}
