<?php

namespace Tests\Feature;

use App\Services\TransactionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('borrowers');

        Schema::create('borrowers', function (Blueprint $table): void {
            $table->id('borrower_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address');
            $table->string('contact');
            $table->timestamp('created_at')->nullable();
            $table->unique(['first_name', 'last_name', 'contact'], 'identity');
        });
    }

    public function test_existing_borrower_is_reused_when_address_changes(): void
    {
        $existingBorrowerId = DB::table('borrowers')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Man',
            'address' => 'Old address',
            'contact' => '09684017322',
        ], 'borrower_id');

        $borrowerId = TransactionService::findOrCreateBorrower(
            'Test',
            'Man',
            'Manila',
            '09684017322',
        );

        $this->assertSame($existingBorrowerId, $borrowerId);
        $this->assertDatabaseCount('borrowers', 1);
    }
}
