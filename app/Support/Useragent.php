<?php

namespace App\Support;

/**
 * Turns a browser "user agent" string into something readable: "Chrome on Windows".
 * Good enough for the signed-in devices list — no package needed.
 */
class UserAgent
{
    /** @return array{browser:string, platform:string, label:string, icon:string, mobile:bool} */
    public static function describe(?string $ua): array
    {
        $ua = (string) $ua;

        $browser = match (true) {
            $ua === ''                                 => 'Unknown browser',
            str_contains($ua, 'Edg/')                  => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser')        => 'Samsung Internet',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'CriOS'), str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/')               => 'Safari',
            default                                    => 'Browser',
        };

        $platform = match (true) {
            str_contains($ua, 'iPhone')                => 'iPhone',
            str_contains($ua, 'iPad')                  => 'iPad',
            str_contains($ua, 'Android')               => 'Android',
            str_contains($ua, 'Windows')               => 'Windows',
            str_contains($ua, 'Macintosh'), str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'CrOS')                  => 'Chromebook',
            str_contains($ua, 'Linux')                 => 'Linux',
            default                                    => 'unknown device',
        };

        $mobile = in_array($platform, ['iPhone', 'Android'], true) || str_contains($ua, 'Mobile');
        $icon   = match (true) {
            $platform === 'iPad'  => 'bi-tablet',
            $mobile               => 'bi-phone',
            default               => 'bi-laptop',
        };

        return ['browser' => $browser, 'platform' => $platform, 'label' => "{$browser} on {$platform}", 'icon' => $icon, 'mobile' => $mobile];
    }
}