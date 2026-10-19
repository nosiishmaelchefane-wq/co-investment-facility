<?php

namespace App\Services;

/**
 * Internal screening logic for the Enterprise Expression of Interest.
 * The applicant never sees this; it classifies each submission into an
 * indicative next step (per the EOI form's "Internal screening logic" table).
 */
class EoiScreeningService
{
    public const CI_APPLICATION   = 'ci_application';
    public const FACILITATION     = 'transaction_facilitation';
    public const MATCHMAKING      = 'investor_matchmaking';
    public const READINESS        = 'readiness_pathway';
    public const NOT_PROGRESSED   = 'not_progressed';

    public const LABELS = [
        self::CI_APPLICATION => 'Invite to CI Application',
        self::FACILITATION   => 'Transaction Facilitation / Readiness Support',
        self::MATCHMAKING    => 'Investor Matchmaking',
        self::READINESS      => 'Enterprise Development / Readiness Pathway',
        self::NOT_PROGRESSED => 'Not progressed / Refer to other support',
    ];

    /**
     * @param  array  $eoi  Validated EOI data (same keys as the form fields).
     * @return array{outcome: string, label: string, reasons: array<int, string>}
     */
    public function classify(array $eoi): array
    {
        $legal    = $eoi['legal_status']      ?? null;
        $investor = $eoi['investor_status']   ?? null;
        $evidence = (array) ($eoi['investor_evidence'] ?? []);
        $support  = $eoi['support_need']      ?? null;
        $reasons  = [];

        // 1. Basic programme requirements
        if ($legal === 'not_registered' || empty($eoi['declaration_agree'])) {
            $reasons[] = $legal === 'not_registered' ? 'Business is not registered in Lesotho.' : 'Declaration not accepted.';
            return $this->result(self::NOT_PROGRESSED, $reasons);
        }

        $eligible  = $legal === 'registered_operating';
        $nearReady = in_array($legal, ['registered_operating', 'registered_not_operating'], true);

        $hasCommitment = in_array($investor, ['term_sheet', 'committed'], true)
            || (bool) array_intersect($evidence, ['term_sheet', 'investment_agreement']);

        $hasInterest = in_array($investor, ['discussions', 'interest', 'loi', 'other'], true);

        // 2. Eligible + investor commitment / Term Sheet → CI Application
        if ($eligible && $hasCommitment) {
            $reasons[] = 'Registered and operating, with investor commitment or Term Sheet.';
            return $this->result(self::CI_APPLICATION, $reasons);
        }

        // 3. Eligible/near-ready + investor interest, deal not developed → Facilitation
        if ($nearReady && ($hasInterest || $hasCommitment)) {
            $reasons[] = 'Investor interest exists but the transaction is not yet sufficiently developed.';
            return $this->result(self::FACILITATION, $reasons);
        }

        // 4. Investment-ready + no investor → Matchmaking
        if ($eligible && $investor === 'none' && in_array($support, ['find_investor', 'assess_me', 'ci_facility'], true)) {
            $reasons[] = 'Registered and operating with no investor yet.';
            return $this->result(self::MATCHMAKING, $reasons);
        }

        // 5. Everything else → Readiness pathway
        $reasons[] = 'Significant readiness gaps (registration, operations or investment readiness).';
        return $this->result(self::READINESS, $reasons);
    }

    private function result(string $outcome, array $reasons): array
    {
        return ['outcome' => $outcome, 'label' => self::LABELS[$outcome], 'reasons' => $reasons];
    }
}