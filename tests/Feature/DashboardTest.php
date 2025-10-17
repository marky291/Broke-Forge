<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that guests cannot access the dashboard.
     */
    public function test_guest_cannot_access_dashboard(): void
    {
        // Act
        $response = $this->get('/dashboard');

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test that authenticated and verified user can access dashboard.
     */
    public function test_authenticated_verified_user_can_access_dashboard(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert
        $response->assertStatus(200);
    }
}
