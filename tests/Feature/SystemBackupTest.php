<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SystemBackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class SystemBackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['sessions', 'system_settings', 'users', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id('role_id');
            $table->string('role_name');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id('user_id');
            $table->string('username');
            $table->string('password');
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->string('setting_value');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor');
            $table->string('role');
            $table->string('action');
            $table->string('type');
            $table->string('information');
            $table->dateTime('datetime');
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->text('payload');
        });

        Storage::fake('public');
    }

    public function test_backup_contains_database_records_and_managed_uploaded_images(): void
    {
        DB::table('roles')->insert(['role_name' => 'Admin']);
        DB::table('users')->insert(['username' => 'backup-admin', 'password' => 'hashed-password']);
        DB::table('system_settings')->insert(['setting_key' => 'system_name', 'setting_value' => 'Equipment System']);
        DB::table('sessions')->insert(['id' => 'session-id', 'payload' => 'active-session']);
        Storage::disk('public')->put('profile-photos/admin.png', $this->pngImage());

        $path = app(SystemBackupService::class)->create();

        try {
            $archive = new ZipArchive;
            $this->assertSame(true, $archive->open($path));
            $manifest = json_decode($archive->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $userRecords = json_decode($archive->getFromName('database/users.json'), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(1, $manifest['format_version']);
            $this->assertArrayNotHasKey('sessions', $manifest['tables']);
            $this->assertSame('backup-admin', $userRecords[0]['username']);
            $this->assertNotFalse($archive->getFromName('files/profile-photos/admin.png'));
            $archive->close();
        } finally {
            unlink($path);
        }
    }

    public function test_backup_only_includes_tables_from_the_current_database_schema(): void
    {
        DB::statement("ATTACH DATABASE ':memory:' AS legacy_schema");
        DB::statement('CREATE TABLE legacy_schema.account_access (user_id INTEGER, access_level INTEGER)');
        $path = null;

        try {
            $path = app(SystemBackupService::class)->create();

            $archive = new ZipArchive;
            $this->assertSame(true, $archive->open($path));
            $manifest = json_decode($archive->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $archive->close();

            $this->assertArrayNotHasKey('account_access', $manifest['tables']);
        } finally {
            if ($path !== null && file_exists($path)) {
                unlink($path);
            }

            DB::statement('DETACH DATABASE legacy_schema');
        }
    }

    public function test_restore_replaces_current_records_and_managed_images(): void
    {
        DB::table('roles')->insert(['role_name' => 'Admin']);
        DB::table('users')->insert(['username' => 'restored-user', 'password' => 'hash']);
        DB::table('system_settings')->insert(['setting_key' => 'system_name', 'setting_value' => 'Backup Value']);
        Storage::disk('public')->put('profile-photos/saved.png', $this->pngImage());
        $path = app(SystemBackupService::class)->create();

        try {
            DB::table('users')->insert(['username' => 'newer-user', 'password' => 'other-hash']);
            DB::table('system_settings')->update(['setting_value' => 'Changed Value']);
            Storage::disk('public')->put('profile-photos/newer.png', $this->pngImage());

            app(SystemBackupService::class)->restore($path);

            $this->assertSame(['restored-user'], DB::table('users')->orderBy('username')->pluck('username')->all());
            $this->assertSame('Backup Value', DB::table('system_settings')->value('setting_value'));
            $this->assertTrue(Storage::disk('public')->exists('profile-photos/saved.png'));
            $this->assertFalse(Storage::disk('public')->exists('profile-photos/newer.png'));
        } finally {
            unlink($path);
        }
    }

    public function test_restore_rejects_an_invalid_archive_without_changing_data(): void
    {
        DB::table('users')->insert(['username' => 'keep-user', 'password' => 'hash']);
        $path = tempnam(sys_get_temp_dir(), 'invalid-backup-');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::OVERWRITE);
        $archive->addFromString('manifest.json', '{"format_version":99,"tables":[]}');
        $archive->close();

        $rejected = false;
        try {
            app(SystemBackupService::class)->restore($path);
        } catch (InvalidArgumentException) {
            $rejected = true;
        } finally {
            unlink($path);
        }

        $this->assertTrue($rejected);
        $this->assertSame(['keep-user'], DB::table('users')->pluck('username')->all());
    }

    public function test_authenticated_admin_can_download_a_system_backup(): void
    {
        $this->authenticateAdmin();

        $response = $this->get(route('system.backup'));

        $response->assertDownload();
        $response->assertHeader('content-type', 'application/zip');
    }

    public function test_admin_can_restore_a_backup_after_confirming_replacement(): void
    {
        $this->authenticateAdmin();
        DB::table('users')->insert(['username' => 'restored-user', 'password' => 'saved-hash']);
        DB::table('system_settings')->insert(['setting_key' => 'system_name', 'setting_value' => 'Backup Value']);
        $archivePath = app(SystemBackupService::class)->create();
        $backupContents = file_get_contents($archivePath);
        unlink($archivePath);
        DB::table('users')->insert(['username' => 'newer-user', 'password' => 'other-hash']);
        $backup = UploadedFile::fake()->createWithContent('system_backup.zip', $backupContents);

        $response = $this->post(route('system.restore'), [
            'backup' => $backup,
            'confirmation' => 'RESTORE',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'System data and uploaded images were restored successfully.');
        $this->assertSame(['restored-user'], DB::table('users')->orderBy('username')->pluck('username')->all());
        $this->assertSame('Backup Value', DB::table('system_settings')->value('setting_value'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Restored system backup']);
    }

    private function authenticateAdmin(): void
    {
        $admin = new User([
            'username' => 'admin',
            'access_level' => 0,
        ]);
        $admin->setRelation('role', new Role(['role_name' => 'Admin']));
        $this->actingAs($admin);
    }

    private function pngImage(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('N2C5', 1, 1, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress("\x00\xff\xff\xff\xff"))
            .$chunk('IEND', '');
    }
}
