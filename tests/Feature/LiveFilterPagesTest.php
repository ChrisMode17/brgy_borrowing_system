<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveFilterPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'borrow_items',
            'transactions',
            'borrowers',
            'inventory_status',
            'inventory',
            'inventory_categories',
            'audit_logs',
            'system_settings',
            'users',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->unsignedBigInteger('role_id');
            $table->unsignedTinyInteger('access_level');
            $table->timestamp('created_at')->nullable();
            $table->string('remember_token')->nullable();
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->string('setting_value')->nullable();
        });
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id('category_id');
            $table->string('category_name');
        });
        Schema::create('inventory', function (Blueprint $table): void {
            $table->id('equipment_id');
            $table->string('name');
            $table->unsignedBigInteger('category_id');
            $table->integer('total_quantity');
            $table->unsignedBigInteger('source_equipment_id')->nullable();
        });
        Schema::create('inventory_status', function (Blueprint $table): void {
            $table->unsignedBigInteger('equipment_id');
            $table->string('status');
            $table->integer('quantity');
        });
        Schema::create('borrowers', function (Blueprint $table): void {
            $table->id('borrower_id');
            $table->string('first_name');
            $table->string('last_name');
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->string('refnum')->primary();
            $table->unsignedBigInteger('borrower_id');
            $table->dateTime('dateBorrowed');
            $table->dateTime('returnDate');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('borrow_items', function (Blueprint $table): void {
            $table->id();
            $table->string('refnum');
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->integer('quantity');
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

    public function test_returns_page_uses_live_search_and_returns_a_filtered_partial(): void
    {
        $this->authenticateAdmin();
        $this->createBorrow('RET-100', 'Ada', 'Lovelace', 'borrowed');

        $this->get(route('return.index'))
            ->assertOk()
            ->assertSee('data-live-filter-search', false)
            ->assertSee('data-live-filter-control', false)
            ->assertDontSee('>Clear</a>', false)
            ->assertDontSee('>Search</button>', false);

        $this->withHeader('X-Live-Filter', 'true')
            ->get(route('return.index', ['search' => 'RET-100', 'status' => 'borrowed']))
            ->assertOk()
            ->assertSee('data-live-filter-results', false)
            ->assertSee('RET-100')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('<html', false);
    }

    public function test_manage_users_page_uses_live_filters_and_returns_filtered_partial(): void
    {
        $admin = $this->authenticateAdmin();
        User::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'username' => 'adalovelace',
            'password' => 'password',
            'email' => 'ada@example.com',
            'contact_number' => '1234567890',
            'role_id' => $admin->role_id,
            'access_level' => 3,
        ]);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('data-live-filter-search', false)
            ->assertSee('data-live-filter-control', false)
            ->assertDontSee('>Clear</a>', false)
            ->assertDontSee('>Search</button>', false);

        $this->withHeader('X-Live-Filter', 'true')
            ->get(route('users.index', ['search' => 'Ada', 'access_level' => 3]))
            ->assertOk()
            ->assertSee('data-live-filter-results', false)
            ->assertSee('adalovelace')
            ->assertDontSee('<html', false);
    }

    public function test_manage_equipment_page_uses_live_filters_and_returns_filtered_partial(): void
    {
        $this->authenticateAdmin();
        $categoryId = DB::table('inventory_categories')->insertGetId(['category_name' => 'Equipment']);
        $equipmentId = DB::table('inventory')->insertGetId([
            'name' => 'Microscope',
            'category_id' => $categoryId,
            'total_quantity' => 4,
        ]);
        DB::table('inventory_status')->insert([
            'equipment_id' => $equipmentId,
            'status' => 'available',
            'quantity' => 4,
        ]);

        $this->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('data-live-filter-search', false)
            ->assertSee('data-live-filter-control', false)
            ->assertDontSee('>Search</button>', false);

        $this->withHeader('X-Live-Filter', 'true')
            ->get(route('equipment.index', ['search' => 'Micro', 'category' => 'Equipment', 'status' => 'available']))
            ->assertOk()
            ->assertSee('data-live-filter-results', false)
            ->assertSee('Microscope')
            ->assertDontSee('<html', false);
    }

    public function test_inventory_data_can_return_all_items_for_the_borrow_form(): void
    {
        $this->authenticateAdmin();
        $categoryId = DB::table('inventory_categories')->insertGetId(['category_name' => 'Equipment']);

        for ($index = 1; $index <= 12; $index++) {
            $equipmentId = DB::table('inventory')->insertGetId([
                'name' => "Equipment {$index}",
                'category_id' => $categoryId,
                'total_quantity' => 1,
            ]);
            DB::table('inventory_status')->insert([
                'equipment_id' => $equipmentId,
                'status' => 'available',
                'quantity' => 1,
            ]);
        }

        $this->getJson(route('inventory.data'))
            ->assertOk()
            ->assertJsonCount(10, 'items')
            ->assertJsonPath('pagination.lastPage', 2);

        $this->getJson(route('inventory.data', ['all' => 1]))
            ->assertOk()
            ->assertJsonCount(12, 'items')
            ->assertJsonFragment(['name' => 'Equipment 12'])
            ->assertJsonPath('pagination.lastPage', 1);
    }

    public function test_audit_trail_page_uses_live_filters_and_returns_filtered_partial(): void
    {
        $this->authenticateAdmin();
        DB::table('audit_logs')->insert([
            'actor' => 'Ada Lovelace',
            'role' => 'Admin',
            'action' => 'Updated equipment',
            'type' => 'Equipment',
            'information' => 'Microscope updated',
            'datetime' => '2026-10-04 10:00:00',
        ]);

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertSee('data-live-filter-search', false)
            ->assertSee('data-live-filter-control', false)
            ->assertDontSee('>Filter</button>', false);

        $this->withHeader('X-Live-Filter', 'true')
            ->get(route('audit.index', ['search' => 'Microscope', 'typeFilter' => 'Equipment', 'dateFilter' => '1day']))
            ->assertOk()
            ->assertSee('data-live-filter-results', false)
            ->assertSee('Microscope updated')
            ->assertDontSee('<html', false);
    }

    private function authenticateAdmin(): User
    {
        $role = Role::query()->create(['role_name' => 'Admin']);
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'username' => 'testadmin',
            'password' => 'password',
            'email' => 'admin@example.com',
            'contact_number' => '1234567890',
            'role_id' => $role->role_id,
            'access_level' => 0,
        ]);

        $this->actingAs($user)->travelTo('2026-10-04 11:00:00');

        return $user;
    }

    private function createBorrow(string $reference, string $firstName, string $lastName, string $status): void
    {
        $borrowerId = DB::table('borrowers')->insertGetId([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        DB::table('transactions')->insert([
            'refnum' => $reference,
            'borrower_id' => $borrowerId,
            'dateBorrowed' => '2026-10-03 09:00:00',
            'returnDate' => '2026-10-05 09:00:00',
            'status' => $status,
            'created_at' => '2026-10-03 08:00:00',
        ]);
    }
}
