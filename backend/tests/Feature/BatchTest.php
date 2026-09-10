<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_batches_are_publicly_listable_for_registration(): void
    {
        Batch::factory()->create(['name' => 'Batch AA-2026-A']);
        Batch::factory()->create(['name' => 'Batch BB-2026-B']);

        // Unauthenticated on purpose: the registration form needs valid ids
        // before an account exists.
        $response = $this->getJson('/api/batches');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Batch AA-2026-A')
            ->assertJsonStructure(['data' => [['id', 'name', 'department', 'academic_year']]]);
    }

    public function test_batch_listing_exposes_no_user_data(): void
    {
        $batch = Batch::factory()->create();
        User::factory()->create(['batch_id' => $batch->id]);

        $payload = $this->getJson('/api/batches')->json('data.0');

        // Only descriptive fields — no membership, counts or emails.
        $this->assertSame(['id', 'name', 'department', 'academic_year'], array_keys($payload));
    }

    public function test_registration_accepts_an_id_returned_by_the_batches_endpoint(): void
    {
        Batch::factory()->create();
        $batchId = $this->getJson('/api/batches')->json('data.0.id');

        $this->postJson('/api/auth/register', [
            'name' => 'Wired Student',
            'email' => 'wired@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_id' => $batchId,
        ])->assertCreated()->assertJsonPath('user.batch_id', $batchId);
    }
}
