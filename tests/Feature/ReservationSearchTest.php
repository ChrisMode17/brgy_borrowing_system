<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReservationSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('borrow_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('borrowers');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('system_settings');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id('role_id');
            $table->string('role_name');
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->string('setting_value')->nullable();
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

        Schema::create('inventory', function (Blueprint $table): void {
            $table->id('equipment_id');
            $table->string('name');
        });

        Schema::create('borrow_items', function (Blueprint $table): void {
            $table->id();
            $table->string('refnum');
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->integer('quantity');
        });
    }

    public function test_live_data_search_defaults_to_pending_and_can_filter_by_status(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $this->actingAs((new User)->forceFill(['user_id' => 1]));
        $borrowerId = DB::table('borrowers')->insertGetId([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ], 'borrower_id');
        DB::table('inventory')->insert(['equipment_id' => 1, 'name' => 'Microscope']);
        DB::table('transactions')->insert([
            [
                'refnum' => 'pending-ada',
                'borrower_id' => $borrowerId,
                'dateBorrowed' => '2026-10-04 09:00:00',
                'returnDate' => '2026-10-05 09:00:00',
                'status' => 'pending',
                'created_at' => '2026-10-04 08:00:00',
            ],
            [
                'refnum' => 'borrowed-ada',
                'borrower_id' => $borrowerId,
                'dateBorrowed' => '2026-10-04 09:00:00',
                'returnDate' => '2026-10-05 09:00:00',
                'status' => 'borrowed',
                'created_at' => '2026-10-04 08:00:00',
            ],
        ]);
        DB::table('borrow_items')->insert([
            'refnum' => 'pending-ada',
            'equipment_id' => 1,
            'quantity' => 2,
        ]);

        $this->getJson(route('reservation.data', ['search' => 'Ada']))
            ->assertOk()
            ->assertJsonPath('items.0.refnum', 'pending-ada')
            ->assertJsonPath('items.0.borrowerName', 'Ada Lovelace')
            ->assertJsonPath('items.0.items', 'Microscope x2')
            ->assertJsonPath('items.0.borrowAllowed', true)
            ->assertJsonPath('pagination.total', 1);

        $this->getJson(route('reservation.data', ['search' => 'Ada', 'status' => 'borrowed']))
            ->assertOk()
            ->assertJsonPath('items.0.refnum', 'borrowed-ada')
            ->assertJsonPath('items.0.status', 'borrowed')
            ->assertJsonPath('pagination.total', 1);

        $this->getJson(route('reservation.data', ['search' => 'Ada', 'status' => 'all']))
            ->assertOk()
            ->assertJsonPath('pagination.total', 2);
    }

    public function test_reservations_page_renders_auto_search_and_status_filter_without_submit_button(): void
    {
        $roleId = DB::table('roles')->insertGetId(['role_name' => 'Admin'], 'role_id');
        $user = (new User)->forceFill([
            'user_id' => 1,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'role_id' => $roleId,
            'access_level' => 0,
        ]);
        $this->actingAs($user)->withoutVite();

        $this->get(route('reservation.index'))
            ->assertOk()
            ->assertSee('Search ref no. or borrower...')
            ->assertSee('@input.debounce.400ms="load()"', false)
            ->assertSee('value="all"', false)
            ->assertSee('value="pending"', false)
            ->assertDontSee('Search</button>', false);
    }

    public function test_live_data_rejects_an_unsupported_status_filter(): void
    {
        $this->actingAs((new User)->forceFill(['user_id' => 1]));

        $this->getJson(route('reservation.data', ['status' => 'cancelled']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
