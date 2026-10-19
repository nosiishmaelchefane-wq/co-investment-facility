<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie role + CIF display fields (label, description, type, icon, is_system).
 * Registered in config/permission.php → 'models' => ['role' => App\Models\Role::class]
 *
 * name  = system key used in code (e.g. 'pmu', used by @role / hasRole)
 * label = what people see (e.g. 'PMU')
 */
class Role extends SpatieRole
{
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_EXTERNAL = 'external';

    /** Bypasses every permission check (see AppServiceProvider Gate::before). */
    public const SUPER_ADMIN = 'super_admin';

    /** Role that manages roles — it can never lose this permission (prevents lock-out). */
    public const ADMIN = 'admin';
    public const ADMIN_LOCKED_PERMISSIONS = ['roles.manage', 'users.manage'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: ucfirst(str_replace('_', ' ', $this->name));
    }

    public function isExternal(): bool
    {
        return $this->type === self::TYPE_EXTERNAL;
    }

    /** System roles (seeded from the ToR) can be edited but not deleted or renamed. */
    public function isDeletable(): bool
    {
        return ! $this->is_system;
    }
}