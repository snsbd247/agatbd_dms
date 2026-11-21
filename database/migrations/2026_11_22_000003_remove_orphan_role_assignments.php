<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role and permission rows of users that no longer exist. The old SQLite
 * database had one (a Manager role for a deleted user 5); when a new user
 * later got id 5 it silently inherited Manager rights. Remove any such rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            DB::table($table)
                ->where('model_type', 'App\\Models\\User')
                ->whereNotIn('model_id', DB::table('users')->select('id'))
                ->delete();
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to restore: the rows pointed at users that did not exist.
    }
};
