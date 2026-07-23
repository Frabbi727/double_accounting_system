<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\StockMovement;

/**
 * Realigns the opening cut-off date to match config('shop.cutoff_date').
 *
 * The opening balances, the opening stock movement and the locked opening
 * period are all dated at the OLD cut-off. This shifts them to the NEW
 * cut-off so that daily transactions dated after the new cut-off are allowed.
 * Idempotent: does nothing when old == new. Only touches Opening-tagged rows,
 * so it is safe even after daily entries exist.
 */
class SetCutoffDate extends Command
{
    protected $signature = 'shop:set-cutoff';

    protected $description = 'Realign opening cut-off (period + opening entries + opening stock) to config shop.cutoff_date';

    public function handle(): int
    {
        $newCutoff = config('shop.cutoff_date');

        $period = AccountingPeriod::where('name', 'Opening')->first();

        if (! $period) {
            $this->error('No "Opening" accounting period found. Enter and lock opening first.');

            return self::FAILURE;
        }

        $oldCutoff = $period->end_date->toDateString();
        $new = \Illuminate\Support\Carbon::parse($newCutoff)->toDateString();

        if ($oldCutoff === $new) {
            $this->info("Nothing to do — opening cut-off is already {$new}.");

            return self::SUCCESS;
        }

        [$entries, $stock] = DB::transaction(function () use ($period, $oldCutoff, $new) {
            $entries = JournalEntry::where('reference_type', 'Opening')
                ->whereDate('date', $oldCutoff)
                ->update(['date' => $new]);

            $stock = StockMovement::opening()
                ->whereDate('date', $oldCutoff)
                ->update(['date' => $new]);

            $period->update(['end_date' => $new]);

            return [$entries, $stock];
        });

        $this->info("Opening cut-off realigned: {$oldCutoff} → {$new}");
        $this->line("  • accounting period end_date → {$new} (locked state unchanged)");
        $this->line("  • opening journal entries re-dated: {$entries}");
        $this->line("  • opening stock movements re-dated: {$stock}");
        $this->newLine();
        $this->info("Daily transactions dated after {$new} are now allowed.");

        return self::SUCCESS;
    }
}
