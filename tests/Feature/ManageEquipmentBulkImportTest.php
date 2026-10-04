<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class ManageEquipmentBulkImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('inventory_status');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

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

        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id('category_id');
            $table->string('category_name', 50);
        });

        Schema::create('inventory', function (Blueprint $table): void {
            $table->id('equipment_id');
            $table->string('name', 100);
            $table->unsignedBigInteger('category_id');
            $table->integer('total_quantity');
            $table->unsignedBigInteger('source_equipment_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('inventory_status', function (Blueprint $table): void {
            $table->unsignedBigInteger('equipment_id');
            $table->string('status');
            $table->integer('quantity')->default(0);
            $table->primary(['equipment_id', 'status']);
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

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->text('setting_value')->nullable();
        });
    }

    public function test_csv_upload_imports_rows_with_quoted_values(): void
    {
        $this->authenticateAdmin();
        $file = UploadedFile::fake()->createWithContent(
            'equipment.csv',
            "name,category,totalQuantity\r\n\"First Aid, Large\",Equipment,5\r\n"
        );

        $response = $this->post(route('equipment.bulk-store'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 equipment item(s) added successfully.');
        $this->assertDatabaseHas('inventory', [
            'name' => 'First Aid, Large',
            'total_quantity' => 5,
        ]);
    }

    public function test_excel_upload_imports_data_by_column_name(): void
    {
        $this->authenticateAdmin();
        $path = tempnam(sys_get_temp_dir(), 'equipment-upload-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['totalQuantity', 'name', 'category']));
        $writer->addRow(Row::fromValues([7, 'Excel Equipment', 'Tool']));
        $writer->close();
        $file = new UploadedFile(
            $path,
            'equipment.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            UPLOAD_ERR_OK,
            true
        );

        try {
            $response = $this->post(route('equipment.bulk-store'), ['file' => $file]);
        } finally {
            unlink($path);
        }

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 equipment item(s) added successfully.');
        $this->assertDatabaseHas('inventory', [
            'name' => 'Excel Equipment',
            'total_quantity' => 7,
        ]);
    }

    public function test_csv_template_download_includes_import_headers_and_sample_rows(): void
    {
        $this->authenticateAdmin();

        $response = $this->get(route('equipment.bulk-template.csv'));

        $response->assertDownload('equipment_template.csv');
        $this->assertStringContainsString('name,category,totalQuantity', $response->streamedContent());
        $this->assertStringContainsString('"Sample Equipment",Equipment,5', $response->streamedContent());
    }

    public function test_excel_template_download_is_a_valid_workbook(): void
    {
        $this->authenticateAdmin();

        $response = $this->get(route('equipment.bulk-template.xlsx'));

        $response->assertDownload('equipment_template.xlsx');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;

        try {
            $this->assertSame(true, $archive->open($path));
            $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($sheet);
            $this->assertStringContainsString('Sample Equipment', $sheet);
        } finally {
            $archive->close();
            unlink($path);
        }
    }

    public function test_equipment_search_and_category_filters_apply_before_pagination(): void
    {
        $this->authenticateAdmin();
        for ($number = 1; $number <= 11; $number++) {
            $this->createEquipment(sprintf('Blue Item %02d', $number), 'Equipment');
        }
        $this->createEquipment('Red Equipment', 'Equipment');
        $this->createEquipment('Blue Tool', 'Tool');

        $response = $this->get(route('equipment.index', [
            'search' => 'Blue',
            'category' => 'Equipment',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('Blue Item 11')
            ->assertDontSee('Red Equipment')
            ->assertDontSee('Blue Tool')
            ->assertSee('equipment-search')
            ->assertSee('category=Equipment')
            ->assertSee('Showing 11-11 of 11 equipment items');
    }

    public function test_equipment_status_filter_uses_the_displayed_status_precedence(): void
    {
        $this->authenticateAdmin();
        $maintenanceItem = $this->createEquipment('Maintenance Item', 'Equipment');
        DB::table('inventory_status')->insert([
            'equipment_id' => $maintenanceItem->equipment_id,
            'status' => 'under_maintenance',
            'quantity' => 1,
        ]);
        $availableItem = $this->createEquipment('Available Item', 'Equipment');
        DB::table('inventory_status')->insert([
            ['equipment_id' => $availableItem->equipment_id, 'status' => 'under_maintenance', 'quantity' => 1],
            ['equipment_id' => $availableItem->equipment_id, 'status' => 'available', 'quantity' => 1],
        ]);

        $response = $this->get(route('equipment.index', ['status' => 'under_maintenance']));

        $response->assertOk()
            ->assertSee('Maintenance Item')
            ->assertDontSee('Available Item');
    }

    private function createEquipment(string $name, string $category): Inventory
    {
        $categoryId = DB::table('inventory_categories')->insertGetId(['category_name' => $category]);

        return Inventory::create([
            'name' => $name,
            'category_id' => $categoryId,
            'total_quantity' => 2,
        ]);
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
}
