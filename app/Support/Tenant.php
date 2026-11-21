<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Whose data the logged-in user works with.
 *
 * Every business record belongs either to the head office (dealer_id null)
 * or to one dealer. The global scope in Models\Concerns\BelongsToTenant uses
 * allowedDealerIds() to hide everything else:
 *
 *  - head office staff (Admin, Manager, office users) → [null]
 *  - a dealer's login (users.dealer_id = X)            → [X]
 *  - a head office representative placed with dealers → [null, X, Y …]
 *  - no user (console, queue, seeders), a Retailer login, or inside
 *    Tenant::bypass()                                  → null = no limit
 *
 * Retailer logins are not limited here because every portal page already
 * restricts itself to that retailer's own records.
 */
class Tenant
{
    private static int $bypassDepth = 0;

    /** @var array<int, array<int, int|null>|null> per user id */
    private static array $cache = [];

    /** @return array<int, int|null>|null */
    public static function allowedDealerIds(): ?array
    {
        if (self::$bypassDepth > 0 || ! Auth::hasUser()) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        if (! array_key_exists($user->id, self::$cache)) {
            self::$cache[$user->id] = self::compute($user);
        }

        return self::$cache[$user->id];
    }

    /** The dealer the user belongs to, or null for head office users. */
    public static function dealerId(): ?int
    {
        return Auth::user()?->dealer_id;
    }

    public static function isHeadOffice(): bool
    {
        return Auth::check() && Auth::user()->dealer_id === null;
    }

    /** Run $callback with no tenant limit (e.g. moving stock into a dealer's warehouse). */
    public static function bypass(callable $callback): mixed
    {
        self::$bypassDepth++;

        try {
            return $callback();
        } finally {
            self::$bypassDepth--;
        }
    }

    /** Switch the filter off until enable() (pairs must match). */
    public static function disable(): void
    {
        self::$bypassDepth++;
    }

    public static function enable(): void
    {
        self::$bypassDepth = max(0, self::$bypassDepth - 1);
    }

    /** Back to a clean state (tests). */
    public static function reset(): void
    {
        self::$bypassDepth = 0;
        self::$cache = [];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Limit a query to the allowed owners. $column holds the dealer id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function apply($query, string $column, ?array $allowed = null)
    {
        $allowed ??= self::allowedDealerIds();

        if ($allowed === null) {
            return $query;
        }

        $ids = array_values(array_filter($allowed, fn ($id) => $id !== null));
        $headOffice = in_array(null, $allowed, true);

        return $query->where(function ($w) use ($column, $ids, $headOffice) {
            if ($ids !== []) {
                $w->whereIn($column, $ids);
            }
            if ($headOffice) {
                $w->orWhereNull($column);
            }
            if ($ids === [] && ! $headOffice) {
                $w->whereRaw('1 = 0');
            }
        });
    }

    /** @return array<int, int|null>|null */
    private static function compute(User $user): ?array
    {
        if ($user->dealer_id !== null) {
            return [(int) $user->dealer_id];
        }

        // Retailers see only their own records through the portal.
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->pluck('roles.name')
            ->all();

        if (in_array('Retailer', $roles, true)) {
            return null;
        }

        // Head office representative: also the dealers they are placed with.
        $placed = DB::table('dealer_representative')
            ->join('representatives', 'representatives.id', '=', 'dealer_representative.representative_id')
            ->where('representatives.user_id', $user->id)
            ->pluck('dealer_representative.dealer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [null, ...$placed];
    }
}
