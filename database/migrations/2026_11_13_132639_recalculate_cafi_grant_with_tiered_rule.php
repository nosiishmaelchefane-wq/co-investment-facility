<?php

use App\Models\EnterpriseProfile;
use Illuminate\Database\Migrations\Migration;

/*
| CAFI investment model — the grant is no longer a flat 50% of the investment:
|   50% of the first US$ 300,000 + 33% of the investment above US$ 300,000, never more than US$ 350,000 per transaction
|   (config/cif.php co_investment.grant_tiers / max_grant_usd; the lifetime cap per enterprise is unchanged).
| Recalculate the indicative grant stored on every enterprise profile. Investments up to US$ 300,000 give the same
| grant as before; only larger investments change.
|
| Amounts already recorded at a gate (Gate 2 validated grant, Committee decision, authorised grant, tranches) are NOT
| changed — they were decided by people and stay as recorded.
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
        // The flat-rate figures are not restored; recalculate with the rule in config/cif.php if needed.
    }
};