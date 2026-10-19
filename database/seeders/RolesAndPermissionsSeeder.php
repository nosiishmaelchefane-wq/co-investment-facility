<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * CIF roles & permissions (ToR scope item 13 — Investor and Hub/IP left out for now).
 * Safe to run again: existing rows are updated, nothing is duplicated.
 *
 *   php artisan db:seed --class=RolesAndPermissionsSeeder
 *
 * Set CIF_ADMIN_EMAIL in .env to give that user the System Administrator role.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** module => [name => label] */
    protected array $permissions = [
        'eoi' => [
            'eoi.submit'   => 'Create, edit & submit own EOI',
            'eoi.view-all' => 'View all EOIs',
            'eoi.return'   => 'Return EOI / request clarification',
        ],
        'matchmaking' => [
            'match.manage' => 'Manage investor profiles & matches',
        ],
        'gate1' => [
            'gate1.decide' => 'Screen and record eligibility decision',
        ],
        'gate2' => [
            'gate2.assess' => 'Perform due diligence & ESGC assessment',
            'gate2.memo'   => 'Prepare Investment Appraisal Memo',
        ],
        'gate3' => [
            'gate3.score'  => 'Declare conflicts & score transactions',
            'gate3.decide' => 'Record committee recommendation',
        ],
        'gate4' => [
            'gate4.md' => 'Approve as Managing Director',
            'gate4.ps' => 'Approve as Principal Secretary',
        ],
        'gate5' => [
            'gate5.evidence' => 'Upload condition evidence',
            'gate5.verify'   => 'Verify conditions & record Grant Agreement',
        ],
        'funding' => [
            'capital.upload'   => 'Upload investor capital evidence',
            'capital.verify'   => 'Verify investor capital (per tranche)',
            'disburse.process' => 'Process disbursements',
        ],
        'portfolio' => [
            'portfolio.report'  => 'Submit progress reports',
            'portfolio.monitor' => 'Monitor portfolio & milestones',
        ],
        'reports' => [
            'reports.view'   => 'View dashboards & reports',
            'reports.export' => 'Export datasets',
        ],
        'admin' => [
            'users.manage'    => 'Manage users',
            'roles.manage'    => 'Manage roles & permissions',
            'settings.manage' => 'Configure criteria, weights & checklists',
            'audit.view'      => 'View audit trail',
        ],
    ];

    /** name => [label, type, icon, description, permissions] */
    protected array $roles = [
        'enterprise' => [
            'Enterprise', 'external', 'bi-shop',
            'Submits EOIs, uploads documents and evidence, reports on milestones.',
            ['eoi.submit', 'gate5.evidence', 'capital.upload', 'portfolio.report'],
        ],
        'pmu' => [
            'PMU', 'internal', 'bi-people',
            'Screening, due diligence, matchmaking, conditions precedent and capital verification.',
            ['eoi.view-all', 'eoi.return', 'match.manage', 'gate1.decide', 'gate2.assess', 'gate2.memo',
             'gate5.verify', 'capital.verify', 'portfolio.monitor', 'reports.view', 'reports.export'],
        ],
        'committee' => [
            'Investment Committee', 'internal', 'bi-clipboard2-data',
            'Declares conflicts of interest and scores transactions at Gate 3.',
            ['gate3.score', 'gate3.decide', 'reports.view'],
        ],
        'managing_director' => [
            'CAFI Managing Director', 'internal', 'bi-person-badge',
            'First approval at Gate 4 (final authorisation).',
            ['eoi.view-all', 'gate4.md', 'portfolio.monitor', 'reports.view', 'audit.view'],
        ],
        'principal_secretary' => [
            'MTIBD Principal Secretary', 'internal', 'bi-bank',
            'Second approval at Gate 4, after the Managing Director.',
            ['eoi.view-all', 'gate4.ps', 'reports.view'],
        ],
        'finance' => [
            'Finance', 'internal', 'bi-cash-stack',
            'Processes disbursements once investor capital is verified.',
            ['disburse.process', 'reports.view', 'reports.export'],
        ],
        'admin' => [
            'System Administrator', 'internal', 'bi-shield-lock',
            'Manages users, roles, criteria, weights and settings.',
            ['eoi.view-all', 'reports.view', 'reports.export', 'users.manage', 'roles.manage', 'settings.manage', 'audit.view'],
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions
        $sort = 0;
        foreach ($this->permissions as $module => $items) {
            foreach ($items as $name => $label) {
                Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['label' => $label, 'module' => $module, 'sort' => ++$sort],
                );
            }
        }

        // Roles (system roles: cannot be deleted)
        foreach ($this->roles as $name => [$label, $type, $icon, $description, $permissions]) {
            $role = Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $label, 'type' => $type, 'icon' => $icon, 'description' => $description, 'is_system' => true],
            );

            $role->syncPermissions($permissions);
        }

        // First administrator
        if ($email = env('CIF_ADMIN_EMAIL')) {
            if ($user = User::where('email', $email)->first()) {
                $user->assignRole('admin');
                $this->command?->info("System Administrator role given to {$email}.");
            } else {
                $this->command?->warn("CIF_ADMIN_EMAIL is set but no user has the email {$email}.");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}