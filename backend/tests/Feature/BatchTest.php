<?php

namespace Tests\Feature;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_batches_are_publicly_listable_for_registration(): void
    {
        Batch::factory()->group(BatchYear::Y2027, Department::CCE)->create();
        Batch::factory()->group(BatchYear::Y2028, Department::CSE)->create();

        // Unauthenticated on purpose: the registration form is shown before an
        // account exists.
        $response = $this->getJson('/api/batches');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', '2027 CCE')
            ->assertJsonPath('data.0.batch_year', '2027')
            ->assertJsonPath('data.0.department', 'CCE')
            ->assertJsonStructure(['data' => [['id', 'name', 'batch_year', 'department', 'academic_year']]]);
    }

    public function test_batch_listing_exposes_no_user_data(): void
    {
        $batch = Batch::factory()->create();
        User::factory()->create(['batch_id' => $batch->id]);

        $payload = $this->getJson('/api/batches')->json('data.0');

        // Only descriptive fields — no membership, counts or emails.
        $this->assertSame(
            ['id', 'name', 'batch_year', 'department', 'academic_year'],
            array_keys($payload)
        );
    }

    public function test_registration_options_are_publicly_available(): void
    {
        // Served from the enums, so the form is correct on an empty database.
        $this->assertDatabaseCount('batches', 0);

        $response = $this->getJson('/api/registration-options');

        $response->assertOk()
            ->assertJsonPath('data.batch_years', ['2027', '2028', '2028++', '2029', '2030'])
            ->assertJsonPath('data.departments.0.value', 'CCE')
            ->assertJsonPath('data.departments.1.value', 'CSE')
            ->assertJsonPath('data.roles.0.value', 'student')
            ->assertJsonPath('data.roles.1.value', 'representative')
            ->assertJsonPath('data.roles.1.label', 'Batch Representative');
    }

    public function test_registration_creates_the_group_when_it_does_not_exist_yet(): void
    {
        $this->assertDatabaseCount('batches', 0);

        $options = $this->getJson('/api/registration-options')->json('data');

        $this->postJson('/api/auth/register', [
            'name' => 'Wired Student',
            'email' => 'wired@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_year' => $options['batch_years'][0],
            'department' => $options['departments'][0]['value'],
            'role' => $options['roles'][0]['value'],
        ])->assertCreated()->assertJsonPath('user.batch', '2027 CCE');

        $this->assertDatabaseHas('batches', ['batch_year' => '2027', 'department' => 'CCE']);
    }

    public function test_a_group_pair_can_only_exist_once(): void
    {
        Batch::factory()->group(BatchYear::Y2030, Department::CCE)->create();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Batch::factory()->group(BatchYear::Y2030, Department::CCE)->create();
    }
}
