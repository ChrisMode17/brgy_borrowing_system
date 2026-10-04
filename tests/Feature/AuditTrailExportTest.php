<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class AuditTrailExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->id('role_id');
            $table->string('role_name');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id('user_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username');
            $table->string('password');
            $table->string('email');
            $table->string('contact_number');
            $table->unsignedTinyInteger('role_id');
            $table->unsignedTinyInteger('access_level');
            $table->timestamp('created_at')->nullable();
            $table->string('remember_token')->nullable();
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
    }

    public function test_csv_export_keeps_filters_and_protects_spreadsheet_formulas(): void
    {
        $this->authenticateAdmin();
        $this->createAuditLog('=1+1', 'Filtered action');
        $this->createAuditLog('Another actor', 'Different action');

        $response = $this->get(route('audit.export', [
            'format' => 'csv',
            'search' => 'Filtered action',
        ]));

        $this->assertSame('Filtered action', $response->baseRequest->query('search'));
        $response->assertDownload();
        $response->assertHeaderContains('content-type', 'text/csv');
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
        $this->assertStringNotContainsString('Different action', $response->streamedContent());
    }

    public function test_excel_export_downloads_a_filtered_xlsx_workbook(): void
    {
        $this->authenticateAdmin();
        $this->createAuditLog('=SUM(1,1)', 'Filtered action');
        $this->createAuditLog('Other actor', 'Different action');

        $response = $this->get(route('audit.export', [
            'format' => 'xlsx',
            'search' => 'Filtered action',
        ]));

        $response->assertDownload();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);

        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($path));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringNotContainsString('<f>', $sheet);
        $archive->close();
        unlink($path);
    }

    public function test_pdf_export_downloads_a_filtered_pdf_document(): void
    {
        $this->authenticateAdmin();
        $this->createAuditLog('PDF actor', 'Filtered action');
        $this->createAuditLog('Other actor', 'Unfiltered action');

        $response = $this->get(route('audit.export', [
            'format' => 'pdf',
            'search' => 'Filtered action',
        ]));

        $response->assertDownload();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_export_rejects_an_unsupported_format(): void
    {
        $this->authenticateAdmin();

        $response = $this->get(route('audit.export', ['format' => 'docx']));

        $response->assertRedirect();
        $response->assertSessionHasErrors('format');
    }

    private function authenticateAdmin(): void
    {
        $role = Role::create(['role_name' => 'Admin']);

        $user = User::create([
            'first_name' => 'System',
            'last_name' => 'Admin',
            'username' => 'admin',
            'password' => 'password',
            'email' => 'admin@example.com',
            'contact_number' => '1234567890',
            'role_id' => $role->role_id,
            'access_level' => 0,
        ]);

        $this->actingAs($user);
    }

    private function createAuditLog(string $actor, string $action): void
    {
        AuditLog::create([
            'actor' => $actor,
            'role' => 'User',
            'action' => $action,
            'type' => 'User',
            'information' => 'Audit test entry',
            'datetime' => '2026-10-03 00:00:00',
        ]);
    }
}
