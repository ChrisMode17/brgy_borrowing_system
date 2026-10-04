<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardTransactionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('transactions');
        Schema::dropIfExists('borrowers');

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
            $table->timestamp('created_at');
        });
    }

    public function test_transaction_list_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.transactions'));

        $response->assertRedirect(route('login'));
    }

    public function test_transaction_list_applies_search_status_and_date_filters(): void
    {
        $this->authenticate();
        $aliceId = $this->createBorrower('Alice', 'Smith');
        $bobId = $this->createBorrower('Bob', 'Jones');
        $this->createTransaction('REF-MATCH', $aliceId, 'borrowed', '2026-10-02 10:00:00');
        $this->createTransaction('REF-STATUS', $bobId, 'borrowed', '2026-10-02 10:00:00');
        $this->createTransaction('REF-OLD', $aliceId, 'borrowed', '2026-09-01 10:00:00');
        $this->createTransaction('REF-PENDING', $aliceId, 'pending', '2026-10-02 10:00:00');

        $response = $this->get(route('dashboard.transactions', [
            'search' => 'Alice',
            'status' => 'borrowed',
            'from' => '2026-10-01',
            'to' => '2026-10-03',
        ]));

        $response->assertOk()
            ->assertSee('REF-MATCH')
            ->assertDontSee('REF-STATUS')
            ->assertDontSee('REF-OLD')
            ->assertDontSee('REF-PENDING');
    }

    public function test_transaction_list_paginates_results_and_keeps_filters_in_links(): void
    {
        $this->authenticate();
        $borrowerId = $this->createBorrower('Taylor', 'Borrower');

        for ($transactionNumber = 1; $transactionNumber <= 11; $transactionNumber++) {
            $this->createTransaction(
                sprintf('REF-%03d', $transactionNumber),
                $borrowerId,
                'borrowed',
                sprintf('2026-10-%02d 10:00:00', $transactionNumber),
            );
        }

        $response = $this->get(route('dashboard.transactions', [
            'status' => 'borrowed',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('REF-001')
            ->assertDontSee('REF-002')
            ->assertSee('status=borrowed', false);
    }

    private function authenticate(): void
    {
        $user = new User;
        $user->user_id = 1;

        $this->actingAs($user);
    }

    private function createBorrower(string $firstName, string $lastName): int
    {
        return DB::table('borrowers')->insertGetId([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ], 'borrower_id');
    }

    private function createTransaction(string $refnum, int $borrowerId, string $status, string $borrowedAt): void
    {
        DB::table('transactions')->insert([
            'refnum' => $refnum,
            'borrower_id' => $borrowerId,
            'dateBorrowed' => $borrowedAt,
            'returnDate' => '2026-10-05 10:00:00',
            'status' => $status,
            'created_at' => $borrowedAt,
        ]);
    }
}
