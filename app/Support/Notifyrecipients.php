<?php

namespace App\Support;

use App\Models\CommitteeReview;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

/**
 * Who gets the CIF staff e-mails, and a safe way to send them.
 *   committee()  → Investment Committee members (role "committee") + whoever keeps the record (gate3.decide)
 *   deciders()   → gate3.decide (Committee secretary / chair)
 *   pmu()        → gate2.assess (PMU officers)
 *   finance()    → disburse.process (Finance)
 * Only active users with an e-mail address. A permission that doesn't exist yet is skipped, not an error.
 */
class NotifyRecipients
{
    public static function committee(): Collection
    {
        $members = rescue(fn () => CommitteeReview::members(), collect(), report: false);

        return self::unique($members->merge(self::withPermission('gate3.decide')));
    }

    public static function deciders(): Collection
    {
        return self::unique(self::withPermission('gate3.decide'));
    }

    public static function pmu(): Collection
    {
        return self::unique(self::withPermission('gate2.assess'));
    }

    /** Finance (disburse.process) — releases tranches after Gate 5 */
    public static function finance(): Collection
    {
        return self::unique(self::withPermission('disburse.process'));
    }

    /** Users holding any of the given permissions, merged */
    public static function anyOf(array $permissions): Collection
    {
        return self::unique(collect($permissions)->flatMap(fn ($p) => self::withPermission($p)));
    }

    /** Users holding a permission (directly or through a role); empty if the permission doesn't exist */
    public static function withPermission(string $permission): Collection
    {
        return rescue(
            fn () => User::permission($permission)->get(),
            collect(),
            report: false
        );
    }

    /**
     * Send to a group and return a short note for the officer, e.g. "The Investment Committee (3) was e-mailed."
     * $except: a user id to leave out (usually the person who triggered the e-mail).
     */
    public static function send(Collection $users, Notification $notification, string $who, ?int $except = null): string
    {
        $users = $users->when($except, fn ($c) => $c->reject(fn ($u) => (int) $u->id === $except))->values();

        if ($users->isEmpty()) {
            return "No {$who} to e-mail were found.";
        }

        try {
            NotificationFacade::send($users, $notification);

            return ucfirst($who).' ('.$users->count().') was e-mailed.';
        } catch (\Throwable $e) {
            report($e);

            return "The e-mail to {$who} could not be sent.".self::debugReason($e);
        }
    }

    /** While APP_DEBUG=true, show why an e-mail failed (the full error is always in storage/logs/laravel.log) */
    public static function debugReason(\Throwable $e): string
    {
        return config('app.debug') ? ' ['.class_basename($e).': '.Str::limit($e->getMessage(), 200).']' : '';
    }

    protected static function unique(Collection $users): Collection
    {
        return $users
            ->filter(fn ($u) => filled($u->email))
            ->filter(fn ($u) => ! defined(User::class.'::STATUS_INACTIVE') || ($u->status ?? null) !== User::STATUS_INACTIVE)
            ->unique('id')
            
            ->values();
    }
}