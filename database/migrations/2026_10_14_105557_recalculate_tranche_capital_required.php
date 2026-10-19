<?php

use App\Models\ConditionReview;
use Illuminate\Database\Migrations\Migration;

/*
| One-off fix: "Investor capital needed" on unreleased tranches could keep an old figure after a tranche amount
| was lowered (e.g. T2 still needing M 800,000 after its amount changed). Reset every unreleased tranche to the
| matching-rule minimum: grant released up to and including the tranche ÷ the grant ratio.
*/
return new class extends Migration
{
    public function up(): void
    {
        ConditionReview::with('tranches')->get()->each(function (ConditionReview $review) {
            $mins = $review->trancheMinimums();

            foreach ($review->tranches as $t) {
                if (! $t->isReleased()) {
                    $t->update(['capital_required' => $mins[$t->id] ?? 0]);
                }
            }
        });
    }

    public function down(): void
    {
        // Nothing to undo
    }
};