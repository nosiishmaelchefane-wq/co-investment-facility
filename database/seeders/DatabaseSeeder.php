<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 *   php artisan db:seed
 *
 * 1. Roles & permissions (RolesAndPermissionsSeeder — safe to run again).
 * 2. The Super Administrator account: nosi.chefane@cbs.co.ls
 *      - roles: super_admin (passes every permission check via Gate::before in AppServiceProvider) + admin
 *      - password: SUPERADMIN_PASSWORD from .env, or a random one printed in the console (first run only)
 *      - running the seeder again never changes an existing password
 */
class DatabaseSeeder extends Seeder
{
    protected const SUPERADMIN_EMAIL = 'nosi.chefane@cbs.co.ls';
    protected const SUPERADMIN_NAME  = 'Nosi Chefane';

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Make sure the Super Administrator role exists (the roles seeder creates it too)
        Role::updateOrCreate(
            ['name' => Role::SUPER_ADMIN, 'guard_name' => 'web'],
            ['label' => 'Super Administrator', 'type' => 'internal', 'icon' => 'bi-stars', 'is_system' => true,
             'description' => 'Full access to everything. For the system owner / developers only.'],
        );

        $user     = User::where('email', self::SUPERADMIN_EMAIL)->first();
        $password = null;

        if (! $user) {
            $password = env('SUPERADMIN_PASSWORD') ?: Str::password(14);

            $user = new User();
            $user->forceFill([
                'name'              => self::SUPERADMIN_NAME,
                'email'             => self::SUPERADMIN_EMAIL,
                'organisation'      => 'CBS (Computer Business Solutions)',
                'password'          => Hash::make($password),
                'status'            => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ])->save();
        } else {
            // Existing account: keep the password, just make sure it can sign in
            $user->forceFill([
                'status'            => User::STATUS_ACTIVE,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        $user->assignRole([Role::SUPER_ADMIN, Role::ADMIN]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Super Administrator: '.self::SUPERADMIN_EMAIL);
        if ($password && ! env('SUPERADMIN_PASSWORD')) {
            $this->command?->warn("Password (shown once — change it after signing in): {$password}");
        } elseif ($password) {
            $this->command?->info('Password: the SUPERADMIN_PASSWORD value from .env');
        } else {
            $this->command?->info('Account already existed — password unchanged.');
        }
    }
}