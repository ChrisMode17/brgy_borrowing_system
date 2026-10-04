<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class DatabaseConnectionFailureTest extends TestCase
{
    public function test_browser_request_returns_safe_503_when_database_connection_is_refused(): void
    {
        $this->registerDatabaseExceptionRoute('SQLSTATE[HY000] [2002] Connection refused');

        $response = $this->get('/database-connection-failure');

        $response->assertStatus(503)
            ->assertSeeText('We’ll be back shortly')
            ->assertSeeText('The service is temporarily unavailable while we restore the connection.')
            ->assertSeeText('Try again')
            ->assertDontSeeText('Connection refused');
    }

    public function test_json_request_returns_safe_503_when_database_connection_is_refused(): void
    {
        $this->registerDatabaseExceptionRoute('SQLSTATE[HY000] [2002] Connection refused');

        $response = $this->getJson('/database-connection-failure');

        $response->assertStatus(503)
            ->assertJson(['message' => 'The service is temporarily unavailable. Please try again later.'])
            ->assertDontSee('Connection refused');
    }

    public function test_non_connection_query_errors_are_not_reported_as_database_unavailable(): void
    {
        $this->registerDatabaseExceptionRoute('SQLSTATE[HY000]: General error: 1 Invalid SQL syntax');

        $response = $this->get('/database-connection-failure');

        $response->assertStatus(500);
    }

    private function registerDatabaseExceptionRoute(string $databaseError): void
    {
        Route::get('/database-connection-failure', function () use ($databaseError): never {
            throw new QueryException(
                'sqlite',
                'select * from users',
                [],
                new PDOException($databaseError),
            );
        });
    }
}
