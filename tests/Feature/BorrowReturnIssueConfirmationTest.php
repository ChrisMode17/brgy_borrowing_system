<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BorrowReturnIssueConfirmationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['return_issues', 'borrow_items', 'transactions', 'borrowers'] as $table) {
            Schema::dropIfExists($table);
        }

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
    }

    public function test_reservation_is_rejected_until_borrower_return_issues_are_acknowledged(): void
    {
        $user = new User;
        $user->user_id = 1;
        $this->actingAs($user);

        $borrowerId = DB::table('borrowers')->insertGetId([
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'address' => 'Main Street',
            'contact' => '12345678',
        ], 'borrower_id');
        DB::table('transactions')->insert([
            'refnum' => 'OLD-REF',
            'borrower_id' => $borrowerId,
            'dateBorrowed' => '2026-09-01 09:00:00',
            'returnDate' => '2026-09-02 09:00:00',
            'status' => 'returned',
        ]);
        $borrowItemId = DB::table('borrow_items')->insertGetId([
            'refnum' => 'OLD-REF',
            'quantity' => 0,
        ]);
        DB::table('return_issues')->insert([
            'borrow_item_id' => $borrowItemId,
            'broken_qty' => 1,
            'issue_status' => 'issue',
        ]);
        $borrowDate = now('Asia/Manila')->addDays(5)->format('Y-m-d\TH:i');
        $returnDate = now('Asia/Manila')->addDays(6)->format('Y-m-d\TH:i');

        $response = $this->from(route('borrow.create'))->post(route('borrow.store'), [
            'fname' => 'Jordan',
            'lname' => 'Lee',
            'addr' => 'Main Street',
            'contNum' => '12345678',
            'dateBorrowed' => $borrowDate,
            'returnDate' => $returnDate,
            'borrowItems' => [1],
            'borrowQuantities' => [1],
        ]);

        $response->assertRedirect(route('borrow.create'))
            ->assertSessionHas('error', 'Review the borrower’s return issues and confirm before submitting this reservation.');
        $this->assertDatabaseCount('transactions', 1);
    }
}
