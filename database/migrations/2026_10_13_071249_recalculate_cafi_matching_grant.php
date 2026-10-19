<?php

use App\Models\EnterpriseProfile;
use Illuminate\Database\Migrations\Migration;

/*
| The CAFI grant is no longer typed in by the applicant — it is the configured share (50%) of what the investor puts in.
| Recalculate it on existing profiles. (No schema change: grant_requested now just holds the calculated figure.)
*/
return new class extends Migration
{
    public function up(): void
    {
        EnterpriseProfile::query()->each(function (EnterpriseProfile $p) {
            $p->forceFill(['grant_requested' => EnterpriseProfile::matchingGrant($p->investorAmount())])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Applicant-entered amounts can't be restored
    }
};