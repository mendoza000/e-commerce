<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Perez',
            'email' => 'juan@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+584121234567',
            'document_type' => 'V',
            'document_number' => '12345678',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Register
    // -----------------------------------------------------------------

    public function test_a_customer_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/customer/register', $this->registrationPayload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'juan@example.test')
            ->assertJsonPath('data.name', 'Juan Perez')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('customers', ['email' => 'juan@example.test']);
    }

    public function test_the_password_is_hashed(): void
    {
        $this->postJson('/api/customer/register', $this->registrationPayload())->assertCreated();

        $customer = Customer::query()->where('email', 'juan@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $customer->password));
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $response = $this->postJson('/api/customer/register', $this->registrationPayload([
            'password_confirmation' => 'somethingelse',
        ]));

        $response->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['password']]]);
    }

    public function test_the_email_must_be_unique(): void
    {
        Customer::factory()->create(['email' => 'juan@example.test']);

        $response = $this->postJson('/api/customer/register', $this->registrationPayload());

        $response->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['email']]]);
    }

    public function test_the_phone_must_match_the_venezuelan_format(): void
    {
        $response = $this->postJson('/api/customer/register', $this->registrationPayload([
            'phone' => '04121234567',
        ]));

        $response->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['phone']]]);
    }

    // -----------------------------------------------------------------
    // Login
    // -----------------------------------------------------------------

    public function test_a_customer_can_log_in_with_correct_credentials(): void
    {
        Customer::factory()->create([
            'email' => 'juan@example.test',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/customer/login', [
            'email' => 'juan@example.test',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'data' => ['id', 'email']]);
    }

    public function test_a_wrong_password_is_rejected_with_a_generic_message(): void
    {
        Customer::factory()->create([
            'email' => 'juan@example.test',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/customer/login', [
            'email' => 'juan@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.fields.email.0', 'Las credenciales no son válidas.');
    }

    /**
     * The same message as a wrong password: telling the two apart would turn
     * this endpoint into an account enumerator (docs/decisions.md, same rule
     * as the admin LoginRequest).
     */
    public function test_a_nonexistent_email_gets_the_same_generic_message(): void
    {
        $response = $this->postJson('/api/customer/login', [
            'email' => 'nadie@example.test',
            'password' => 'whatever123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.fields.email.0', 'Las credenciales no son válidas.');
    }

    public function test_a_customer_with_no_password_set_cannot_log_in(): void
    {
        Customer::factory()->create(['email' => 'juan@example.test', 'password' => null]);

        $response = $this->postJson('/api/customer/login', [
            'email' => 'juan@example.test',
            'password' => 'anything123',
        ]);

        $response->assertStatus(422);
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        Customer::factory()->create([
            'email' => 'juan@example.test',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/customer/login', [
                'email' => 'juan@example.test',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/customer/login', [
            'email' => 'juan@example.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
        $this->assertStringContainsString(
            'Demasiados intentos',
            $response->json('error.fields.email.0'),
        );
    }

    // -----------------------------------------------------------------
    // Me / logout
    // -----------------------------------------------------------------

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/customer/me')->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_customer(): void
    {
        $customer = Customer::factory()->create(['email' => 'juan@example.test']);
        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/customer/me');

        $response->assertOk()->assertJsonPath('data.email', 'juan@example.test');
    }

    public function test_logout_revokes_the_token(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/logout')
            ->assertNoContent();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/customer/me')
            ->assertStatus(401);
    }

    public function test_document_type_is_validated_against_the_enum(): void
    {
        $response = $this->postJson('/api/customer/register', $this->registrationPayload([
            'document_type' => 'X',
        ]));

        $response->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['document_type']]]);
    }
}
