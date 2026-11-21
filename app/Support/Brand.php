<?php

namespace App\Support;

use App\Models\Settings\CompanyProfile;

/**
 * The business name and logo shown on the login page, sidebar, tab titles
 * and PDFs. Both come from Settings → Company Profile; until a profile is
 * saved the app falls back to APP_NAME and no logo.
 */
class Brand
{
    public static function name(): string
    {
        return self::profile()['name'];
    }

    public static function logoUrl(): ?string
    {
        return self::profile()['logo'];
    }

    /** "Page · Company", or just the company name when there is no page title. */
    public static function title(?string $page = null): string
    {
        return $page ? $page.' · '.self::name() : self::name();
    }

    private static function profile(): array
    {
        return once(function () {
            try {
                $profile = CompanyProfile::first();
            } catch (\Throwable) {
                $profile = null; // table not migrated yet (fresh install)
            }

            return [
                'name' => $profile?->name ?: config('app.name', 'DMS'),
                'logo' => $profile?->logoUrl(),
            ];
        });
    }
}
