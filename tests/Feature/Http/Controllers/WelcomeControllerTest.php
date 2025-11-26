<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that guest can view the homepage.
     */
    public function test_guest_can_view_homepage(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('welcome')
        );
    }

    /**
     * Test that authenticated user can view the homepage.
     */
    public function test_authenticated_user_can_view_homepage(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('welcome')
        );
    }

    /**
     * Test that homepage receives subscription plans from database.
     */
    public function test_homepage_receives_subscription_plans(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'amount' => 1500,
            'currency' => 'eur',
            'interval' => 'month',
            'server_limit' => 10,
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('plans', 1)
            ->where('plans.0.name', 'Pro')
            ->where('plans.0.slug', 'pro')
            ->where('plans.0.features.0', 'Feature 1')
        );
    }

    /**
     * Test that homepage only receives active plans.
     */
    public function test_homepage_only_receives_active_plans(): void
    {
        SubscriptionPlan::factory()->create([
            'name' => 'Active Plan',
            'is_active' => true,
        ]);
        SubscriptionPlan::factory()->create([
            'name' => 'Inactive Plan',
            'is_active' => false,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('plans', 1)
            ->where('plans.0.name', 'Active Plan')
        );
    }

    /**
     * Test that homepage receives free plan configuration.
     */
    public function test_homepage_receives_free_plan_config(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('freePlan')
            ->has('freePlan.name')
            ->has('freePlan.server_limit')
            ->has('freePlan.features')
        );
    }

    /**
     * Test that homepage returns empty plans array when no plans exist.
     */
    public function test_homepage_returns_empty_plans_when_none_exist(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('plans', 0)
        );
    }

    /**
     * Test that the home route is named correctly.
     */
    public function test_home_route_is_named(): void
    {
        $this->assertEquals('/', route('home', [], false));
    }
}
