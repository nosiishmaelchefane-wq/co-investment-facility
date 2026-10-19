<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INVITED  = 'invited';
    public const STATUS_INACTIVE = 'inactive';

    /** label, badge class, icon */
    public const STATUSES = [
        self::STATUS_ACTIVE   => ['Active',   'status-approved',  'bi-check-circle'],
        self::STATUS_INVITED  => ['Invited',  'status-in-review', 'bi-envelope'],
        self::STATUS_INACTIVE => ['Inactive', 'status-draft',     'bi-slash-circle'],
    ];

    /** Account-setup / reset links stay valid for this many hours */
    public const SETUP_LINK_HOURS = 72;

    /** Guard Spatie uses for roles / permissions (matches the seeder). */
    protected string $guard_name = 'web';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'enterprise_name',
        'organisation',
        'email',
        'phone',
        'password',
        'status',
        'invited_at',
        'last_login_at',
        'two_factor_enabled',
        'two_factor_changed_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at'        => 'datetime',
            'last_login_at'     => 'datetime',
            'two_factor_enabled'    => 'boolean',
            'two_factor_changed_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /* ---------------- Relations ---------------- */

    /** Enterprise profile (enterprise users only). */
    public function enterpriseProfile()
    {
        return $this->hasOne(EnterpriseProfile::class);
    }

    /** EOIs submitted by this user (enterprise). */
    public function expressionsOfInterest()
    {
        return $this->hasMany(ExpressionOfInterest::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $query->when($term !== '', fn ($q) => $q->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('organisation', 'like', "%{$term}%")
              ->orWhere('enterprise_name', 'like', "%{$term}%");
        }));
    }

    /* ---------------- Helpers ---------------- */

    /**
     * Must this user enter an e-mailed code when signing in?
     * Only when two-step sign-in is on for the platform (config cif.login.otp_enabled) AND the user hasn't switched it off.
     */
    public function requiresSignInCode(): bool
    {
        return (bool) config('cif.login.otp_enabled', false) && ($this->two_factor_enabled ?? true);
    }

    public function isActive(): bool
    {
        return $this->status !== self::STATUS_INACTIVE;
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'U';
    }

    public function getOrganisationNameAttribute(): ?string
    {
        return $this->organisation ?: $this->enterprise_name;
    }

    /** The user's (single) CIF role, or null. */
    public function getPrimaryRoleAttribute(): ?Role
    {
        return $this->roles->firstWhere('name', '!=', Role::SUPER_ADMIN) ?? $this->roles->first();
    }

    /** Active users holding the System Administrator role. */
    public static function activeAdminCount(?int $exceptId = null): int
    {
        return static::role(Role::ADMIN)
            ->where('status', '!=', self::STATUS_INACTIVE)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->count();
    }

    /**
     * Signed link to set a password (used for invitations AND resets).
     * The hash changes when the password changes, so each link works once.
     */
    public function accountSetupUrl(): string
    {
        return URL::temporarySignedRoute(
            'account.setup',
            now()->addHours(self::SETUP_LINK_HOURS),
            ['user' => $this->getKey(), 'h' => $this->setupHash()],
        );
    }

    public function setupHash(): string
    {
        return substr(hash_hmac('sha256', $this->getKey().'|'.$this->email.'|'.$this->getAuthPassword(), config('app.key')), 0, 20);
    }
}