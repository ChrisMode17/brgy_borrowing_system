<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BorrowReservationAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'return_issues', 'borrow_items', 'transactions', 'borrowers', 'inventory_status', 'inventory', 'inventory_categories'] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('inventory_status', function (Blueprint $table): void {
            $table->unsignedBigInteger('equipment_id');
            $table->string('status');
            $table->integer('quantity')->default(0);
            $table->primary(['equipment_id', 'status']);
        });

        Schema::create('borrowers', function (Blueprint $table): void {
            $table->id('borrower_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address');
            $table->string('contact');
            $table->timestamp('created_at')->nullable();
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
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('return_issues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('borrow_item_id');
            $table->integer('normal_qty')->default(0);
            $table->integer('broken_qty')->default(0);
            $table->integer('maintenance_qty')->default(0);
            $table->string('issue_status');
            $table->timestamp('created_at')->nullable();
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

    public function test_inventory_feed_excludes_pending_reservations_for_the_same_calendar_day(): void
    {
        $equipmentId = $this->createInventory(100);
        $this->createPendingReservation($equipmentId, 50, '2026-10-06 08:00:00', '2026-10-06 09:00:00');
        $this->authenticate();

        $response = $this->getJson(route('inventory.data', [
            'borrowDate' => '2026-10-06T15:00',
            'returnDate' => '2026-10-06T16:00',
            'all' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('items.0.futureAvailable', 50);
    }

    public function test_reservation_rejects_quantity_above_same_day_availability(): void
    {
        $equipmentId = $this->createInventory(100);
        $this->createPendingReservation($equipmentId, 50, '2026-10-06 08:00:00', '2026-10-06 09:00:00');
        $this->authenticate();
        $this->freezeReservationTime();

        $response = $this->from(route('borrow.create'))->post(route('borrow.store'), $this->reservationPayload($equipmentId, [51]));

        $response->assertRedirect(route('borrow.create'))
            ->assertSessionHas('error', 'Cannot reserve 51 Chairs. Only 50 unit(s) available during your requested borrow dates.');
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('borrow_items', 1);
    }

    public function test_reservation_allows_exact_available_quantity_and_combines_duplicate_item_rows(): void
    {
        $equipmentId = $this->createInventory(100);
        $this->createPendingReservation($equipmentId, 50, '2026-10-06 08:00:00', '2026-10-06 09:00:00');
        $this->authenticate();
        $this->freezeReservationTime();

        $response = $this->post(route('borrow.store'), $this->reservationPayload($equipmentId, [25, 25]));

        $response->assertRedirect(route('reservation.index'))
            ->assertSessionHas('success', 'Reservation saved successfully.');
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('borrow_items', [
            'equipment_id' => $equipmentId,
            'quantity' => 50,
        ]);
        $this->assertDatabaseCount('borrow_items', 2);
    }

    public function test_reservation_rejects_duplicate_rows_when_their_combined_quantity_exceeds_availability(): void
    {
        $equipmentId = $this->createInventory(100);
        $this->authenticate();
        $this->freezeReservationTime();

        $response = $this->from(route('borrow.create'))->post(route('borrow.store'), $this->reservationPayload($equipmentId, [60, 41]));

        $response->assertRedirect(route('borrow.create'))
            ->assertSessionHas('error', 'Cannot reserve 101 Chairs. Only 100 unit(s) available during your requested borrow dates.');
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('borrowers', 0);
    }

    private function createInventory(int $quantity): int
    {
        $categoryId = DB::table('inventory_categories')->insertGetId(['category_name' => 'Furniture']);
        $equipmentId = DB::table('inventory')->insertGetId([
            'name' => 'Chairs',
            'category_id' => $categoryId,
            'total_quantity' => $quantity,
        ], 'equipment_id');
        DB::table('inventory_status')->insert([
            'equipment_id' => $equipmentId,
            'status' => 'available',
            'quantity' => $quantity,
        ]);

        return $equipmentId;
    }

    private function createPendingReservation(int $equipmentId, int $quantity, string $borrowDate, string $returnDate): void
    {
        $borrowerId = DB::table('borrowers')->insertGetId([
            'first_name' => 'Existing',
            'last_name' => 'Borrower',
            'address' => 'Main Street',
            'contact' => '12345678',
        ], 'borrower_id');
        DB::table('transactions')->insert([
            'refnum' => 'EXISTING-RESERVATION',
            'borrower_id' => $borrowerId,
            'dateBorrowed' => $borrowDate,
            'returnDate' => $returnDate,
            'status' => 'pending',
        ]);
        DB::table('borrow_items')->insert([
            'refnum' => 'EXISTING-RESERVATION',
            'equipment_id' => $equipmentId,
            'quantity' => $quantity,
        ]);
    }

    private function authenticate(): void
    {
        $user = new User([
            'username' => 'admin',
            'access_level' => 0,
        ]);
        $user->setRelation('role', new Role(['role_name' => 'Admin']));

        $this->actingAs($user);
    }

    /**
     * @param  list<int>  $quantities
     * @return array<string, mixed>
     */
    private function reservationPayload(int $equipmentId, array $quantities): array
    {
        return [
            'fname' => 'New',
            'lname' => 'Borrower',
            'addr' => 'Main Street',
            'contNum' => '12345678',
            'dateBorrowed' => '2026-10-06T15:00',
            'returnDate' => '2026-10-06T16:00',
            'borrowItems' => array_fill(0, count($quantities), $equipmentId),
            'borrowQuantities' => array_values($quantities),
        ];
    }

    private function freezeReservationTime(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00', 'Asia/Manila'));
    }
}
