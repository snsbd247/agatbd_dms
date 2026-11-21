<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;   // VACUUM INTO cannot run inside the RefreshDatabase transaction

    protected function tearDown(): void
    {
        File::cleanDirectory(storage_path('backups'));
        parent::tearDown();
    }

    public function test_backup_then_restore_brings_data_back(): void
    {
        $p = $this->product(['name' => 'Original Name']);
        $svc = app(BackupService::class);

        $zip = $svc->create('test');
        $this->assertFileExists($zip);
        $this->assertStringStartsWith(storage_path('backups'), $zip, 'backups must go to the test storage');

        $p->update(['name' => 'Changed']);
        $this->product(['name' => 'Added after backup']);

        $result = $svc->restore($zip);

        $this->assertSame('Original Name', $p->fresh()->name);
        $this->assertDatabaseMissing('products', ['name' => 'Added after backup']);
        $this->assertGreaterThan(0, $result['rows']);
        $this->assertFileExists($svc->directory().DIRECTORY_SEPARATOR.$result['safety_backup']);
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        } else {
            // MySQL has no foreign_key_check: verify the links that matter by hand.
            $this->assertSame(0, DB::table('products')->whereNotIn('category_id', DB::table('categories')->select('id'))->count());
            $this->assertSame(0, DB::table('products')->whereNotIn('unit_id', DB::table('units')->select('id'))->count());
        }
    }

    public function test_garbage_file_is_rejected_and_nothing_changes(): void
    {
        $this->product(['name' => 'Keep me']);
        $bad = storage_path('backups/bad.zip');
        File::put($bad, 'not a backup');

        try {
            app(BackupService::class)->restore($bad);
            $this->fail('A garbage file must be rejected');
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseHas('products', ['name' => 'Keep me']);
    }

    public function test_only_valid_backup_names_resolve_to_a_path(): void
    {
        $svc = app(BackupService::class);

        $this->assertNull($svc->path('../../.env'));
        $this->assertNull($svc->path('dms-20261121-000000.zip'));   // valid name, no such file
    }
}
