<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Gate 3 · One Committee secretary keeps the record for the whole Committee.
|
| Before: every Investment Committee member had gate3.decide through the "committee" role, so any member
|         could fill in the review criteria, deliberation, questions, conditions and the outcome.
| Now:    the role keeps gate3.score (conflict declaration + own scorecard). gate3.decide is given directly to the
|         ONE member ticked as "Committee secretary" in user management (User::makeCommitteeSecretary()).
|
| After migrating, open user management and tick "Committee secretary" on one of the three members.
*/
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'committee')->where('guard_name', 'web')->first();
        $perm = Permission::where('name', 'gate3.decide')->where('guard_name', 'web')->first();

        if ($role && $perm && $role->hasPermissionTo($perm)) {
            $role->revokePermissionTo($perm);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::where('name', 'committee')->where('guard_name', 'web')->first();
        $perm = Permission::where('name', 'gate3.decide')->where('guard_name', 'web')->first();

        if ($role && $perm) {
            $role->givePermissionTo($perm);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};