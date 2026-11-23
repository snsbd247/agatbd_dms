<?php

namespace App\Support;

use Spatie\Permission\Models\Role;

/**
 * Roles: the built-in ones (kept in step by RoleSeeder on every deploy, so
 * they are not edited by hand) and custom ones made under Settings → Roles.
 */
class Roles
{
    /** Built-in roles for head-office staff logins. */
    public const STAFF_BUILT_IN = ['Admin', 'Manager', 'Accountant', 'HR Manager', 'Factory Manager'];

    /** Built-in roles given by other screens (dealer, representative, portal). */
    public const OTHER_BUILT_IN = ['Dealer', 'Sales Representative', 'Retailer'];

    public static function isBuiltIn(string $name): bool
    {
        return in_array($name, array_merge(self::STAFF_BUILT_IN, self::OTHER_BUILT_IN), true);
    }

    /** @return array<int, string> */
    public static function custom(): array
    {
        return Role::query()->where('guard_name', 'web')
            ->whereNotIn('name', array_merge(self::STAFF_BUILT_IN, self::OTHER_BUILT_IN))
            ->orderBy('name')->pluck('name')->all();
    }

    /** Roles a head-office staff login can have. @return array<int, string> */
    public static function staff(): array
    {
        return array_merge(self::STAFF_BUILT_IN, self::custom());
    }
}
