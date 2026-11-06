<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * CIF roles & permissions (ToR scope item 13 — Investor left out for now).
 * Gates: 1 Eligibility · 2 Due diligence · 3 Committee · 4 Grant readiness · 5 Final authorisation · 6 Conditions precedent
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
        // Gate 4 · Grant readiness review (§3.5)
        'readiness' => [
            'readiness.review'   => 'Grants Management review, Grant Readiness Memo & Draft Grant Agreement',
            'readiness.hub'      => 'Approve as Hub Manager',
            'readiness.director' => 'Approve as PMU Director of Implementation',
        ],
        // Gate 5 · Final authorisation (§3.6) — names keep the old "gate4." prefix so existing assignments keep working
        'gate4' => [
            'gate4.md' => 'Record the Managing Director’s signature on the Grant Agreement',
            'gate4.ps' => 'Record the Principal Secretary’s signature on the Grant Agreement',
        ],
        // Gate 6 · Conditions precedent & onboarding — names keep the old "gate5." prefix
        'gate5' => [
            'gate5.evidence' => 'Upload condition evidence',
            'gate5.verify'   => 'Verify conditions & record Grant Agreement',
        ],
        'funding' => [
            'capital.upload'   => 'Upload investor capital evidence',
            'capital.verify'   => 'Verify investor capital (per tranche)',
            'disburse.approve' => 'Approve grant payments (Gate 7 payment approval)',
            'disburse.process' => 'Process disbursements — payment instruction & confirmation',
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
            'Declares conflicts of interest and scores transactions at Gate 3. The member marked as Committee secretary also records the Committee decision.',
            ['gate3.score', 'reports.view'],   // gate3.decide is given to the Committee secretary only (User::makeCommitteeSecretary)
        ],
        // Gate 4 · Grant readiness review — three sequential reviewers
        'grants_management' => [
            'Grants Management Representative', 'internal', 'bi-clipboard-check',
            'Gate 4: reviews the grant budget, eligible expenditure, bank account and documents; prepares the Grant Readiness Memo and Draft Grant Agreement.',
            ['eoi.view-all', 'readiness.review', 'reports.view'],
        ],
        'hub_manager' => [
            'Hub Manager', 'internal', 'bi-person-workspace',
            'Gate 4: reviews the Grant Readiness Memo and Draft Grant Agreement and approves the file for submission.',
            ['eoi.view-all', 'readiness.hub', 'portfolio.monitor', 'reports.view'],
        ],
        'pmu_director' => [
            'PMU Director of Implementation', 'internal', 'bi-person-check',
            'Gate 4: approves the transaction to proceed to final authorisation (Gate 5). Gate 7: approves each grant payment.',
            ['eoi.view-all', 'readiness.director', 'disburse.approve', 'portfolio.monitor', 'reports.view', 'reports.export'],
        ],
        'managing_director' => [
            'CAFI Managing Director', 'internal', 'bi-person-badge',
            'Gate 5: reviews the Grant Readiness Memo and Draft Grant Agreement and signs the Grant Agreement first.',
            ['eoi.view-all', 'gate4.md', 'portfolio.monitor', 'reports.view', 'audit.view'],
        ],
        'principal_secretary' => [
            'MTIBD Principal Secretary', 'internal', 'bi-bank',
            'Gate 5: signs the Grant Agreement after the Managing Director — formal approval of the grant.',
            ['eoi.view-all', 'gate4.ps', 'reports.view'],
        ],
        'finance' => [
            'Finance', 'internal', 'bi-cash-stack',
            'Gate 6: confirms disbursement readiness. Gate 7: issues the payment instruction and confirms each payment.',
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

        // Super Administrator: no permissions of its own — passes every check via Gate::before
        Role::updateOrCreate(
            ['name' => Role::SUPER_ADMIN, 'guard_name' => 'web'],
            ['label' => 'Super Administrator', 'type' => 'internal', 'icon' => 'bi-stars', 'is_system' => true,
             'description' => 'Full access to everything. For the system owner / developers only.'],
        );

        // First administrator (gets Super Administrator + System Administrator)
        if ($email = env('CIF_ADMIN_EMAIL')) {
            if ($user = User::where('email', $email)->first()) {
                $user->assignRole([Role::SUPER_ADMIN, 'admin']);
                $this->command?->info("Super Administrator + System Administrator roles given to {$email}.");
            } else {
                $this->command?->warn("CIF_ADMIN_EMAIL is set but no user has the email {$email}.");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}