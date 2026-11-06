<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Spatie permission + CIF display fields (label, module, sort).
 * Registered in config/permission.php → 'models' => ['permission' => App\Models\Permission::class]
 */
class Permission extends SpatiePermission
{
    /** Module key => [label, icon]. Order here = order on screen. */
    public const MODULES = [
        'eoi'         => ['Transaction EOI',                'bi-file-earmark-text'],
        'matchmaking' => ['Matchmaking',                    'bi-arrow-left-right'],
        'gate1'       => ['Gate 1 · Eligibility',           'bi-funnel'],
        'gate2'       => ['Gate 2 · Due diligence',         'bi-search'],
        'gate3'       => ['Gate 3 · Committee',             'bi-clipboard2-data'],
        'readiness'   => ['Gate 4 · Grant readiness',       'bi-clipboard-check'],
        'gate4'       => ['Gate 5 · Final authorisation',   'bi-patch-check'],    // permission names keep the old "gate4." prefix
        'gate5'       => ['Gate 6 · Conditions precedent',  'bi-list-check'],     // permission names keep the old "gate5." prefix
        'funding'     => ['Funding',                        'bi-cash-stack'],
        'portfolio'   => ['Portfolio',                      'bi-graph-up-arrow'],
        'reports'     => ['Reports',                        'bi-bar-chart-line'],
        'admin'       => ['Administration',                 'bi-shield-lock'],
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function getModuleLabelAttribute(): string
    {
        return self::MODULES[$this->module][0] ?? ucfirst((string) $this->module);
    }

    public function getModuleIconAttribute(): string
    {
        return self::MODULES[$this->module][1] ?? 'bi-grid';
    }
}