<?php

namespace Tests\Unit\Http\Requests\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('test@example.com|127.0.0.1');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('test@example.com|127.0.0.1');
        parent::tearDown();
    }

    /**
     * Test validation passes with valid email and password.
     */
    public function test_validation_passes_with_valid_email_and_password(): void
    {
        // Arrange
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $request = new LoginRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when email is missing.
     */
    public function test_validation_fails_when_email_is_missing(): void
    {
        // Arrange
        $data = [
            'password' => 'password123',
        ];

        $request = new LoginRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when email is invalid format.
     */
    public function test_validation_fails_when_email_is_invalid_format(): void
    {
        // Arrange
        $data = [
            'email' => 'not-an-email',
            'password' => 'password123',
        ];

        $request = new LoginRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid email formats.
     */
    public function test_validation_passes_with_valid_email_formats(): void
    {
        // Arrange
        $request = new LoginRequest;

        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            'user_name@subdomain.example.com',
        ];

        foreach ($validEmails as $email) {
            $data = [
                'email' => $email,
                'password' => 'password123',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Email '{$email}' should be valid");
        }
    }

    /**
     * Test validation fails when password is missing.
     */
    public function test_validation_fails_when_password_is_missing(): void
    {
        // Arrange
        $data = [
            'email' => 'test@example.com',
        ];

        $request = new LoginRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various password formats.
     */
    public function test_validation_passes_with_various_password_formats(): void
    {
        // Arrange
        $request = new LoginRequest;

        $validPasswords = [
            'simple',
            'password123',
            'P@ssw0rd!',
            'very-long-password-with-many-characters',
            '12345678',
        ];

        foreach ($validPasswords as $password) {
            $data = [
                'email' => 'test@example.com',
                'password' => $password,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), 'Password should be valid');
        }
    }

    /**
     * Test authenticate succeeds with valid credentials.
     */
    public function test_authenticate_succeeds_with_valid_credentials(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Act
        $request->authenticate();

        // Assert
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    /**
     * Test authenticate clears rate limiter on success.
     */
    public function test_authenticate_clears_rate_limiter_on_success(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Hit the rate limiter once
        RateLimiter::hit($request->throttleKey());
        $this->assertEquals(1, RateLimiter::attempts($request->throttleKey()));

        // Act
        $request->authenticate();

        // Assert
        $this->assertEquals(0, RateLimiter::attempts($request->throttleKey()));
    }

    /**
     * Test authenticate fails with invalid credentials.
     */
    public function test_authenticate_fails_with_invalid_credentials(): void
    {
        // Arrange
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        // Act & Assert
        $this->expectException(ValidationException::class);
        $request->authenticate();
    }

    /**
     * Test authenticate increments rate limiter on failure.
     */
    public function test_authenticate_increments_rate_limiter_on_failure(): void
    {
        // Arrange
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $initialAttempts = RateLimiter::attempts($request->throttleKey());

        // Act
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            // Expected
        }

        // Assert
        $this->assertEquals($initialAttempts + 1, RateLimiter::attempts($request->throttleKey()));
    }

    /**
     * Test ensureIsNotRateLimited allows requests under limit.
     */
    public function test_ensure_is_not_rate_limited_allows_requests_under_limit(): void
    {
        // Arrange
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Hit 4 times (under the limit of 5)
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit($request->throttleKey());
        }

        // Act & Assert - Should not throw exception
        $request->ensureIsNotRateLimited();
        $this->assertTrue(true);
    }

    /**
     * Test ensureIsNotRateLimited throws exception when too many attempts.
     */
    public function test_ensure_is_not_rate_limited_throws_exception_when_too_many_attempts(): void
    {
        // Arrange
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Hit 5 times to reach the limit
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($request->throttleKey());
        }

        // Act & Assert
        $this->expectException(ValidationException::class);
        $request->ensureIsNotRateLimited();
    }

    /**
     * Test ensureIsNotRateLimited dispatches Lockout event.
     */
    public function test_ensure_is_not_rate_limited_dispatches_lockout_event(): void
    {
        // Arrange
        Event::fake();

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Hit 5 times to reach the limit
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($request->throttleKey());
        }

        // Act
        try {
            $request->ensureIsNotRateLimited();
        } catch (ValidationException $e) {
            // Expected
        }

        // Assert
        Event::assertDispatched(Lockout::class);
    }

    /**
     * Test throttleKey generates correct format.
     */
    public function test_throttle_key_generates_correct_format(): void
    {
        // Arrange
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'Test@Example.COM',
            'password' => 'password',
        ], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        // Act
        $key = $request->throttleKey();

        // Assert
        $this->assertEquals('test@example.com|192.168.1.100', $key);
    }

    /**
     * Test throttleKey is case insensitive for email.
     */
    public function test_throttle_key_is_case_insensitive_for_email(): void
    {
        // Arrange
        $request1 = LoginRequest::create('/login', 'POST', [
            'email' => 'TEST@EXAMPLE.COM',
            'password' => 'password',
        ]);

        $request2 = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Act
        $key1 = $request1->throttleKey();
        $key2 = $request2->throttleKey();

        // Assert
        $this->assertEquals($key1, $key2);
    }

    /**
     * Test throttleKey includes IP address.
     */
    public function test_throttle_key_includes_ip_address(): void
    {
        // Arrange
        $request1 = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password',
        ], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        $request2 = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password',
        ], [], [], ['REMOTE_ADDR' => '192.168.1.200']);

        // Act
        $key1 = $request1->throttleKey();
        $key2 = $request2->throttleKey();

        // Assert
        $this->assertNotEquals($key1, $key2);
        $this->assertStringContainsString('192.168.1.100', $key1);
        $this->assertStringContainsString('192.168.1.200', $key2);
    }

    /**
     * Test authenticate respects remember parameter.
     */
    public function test_authenticate_respects_remember_parameter(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember' => true,
        ]);

        // Act
        $request->authenticate();

        // Assert
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new LoginRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test rate limiting resets after successful login.
     */
    public function test_rate_limiting_resets_after_successful_login(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Failed attempts first
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($request->throttleKey());
        }

        $this->assertEquals(3, RateLimiter::attempts($request->throttleKey()));

        // Act - Successful login
        $request->authenticate();

        // Assert - Rate limiter should be cleared
        $this->assertEquals(0, RateLimiter::attempts($request->throttleKey()));
    }

    /**
     * Test multiple failed login attempts reach rate limit.
     */
    public function test_multiple_failed_login_attempts_reach_rate_limit(): void
    {
        // Arrange
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        // Act - 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $request = LoginRequest::create('/login', 'POST', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            try {
                $request->authenticate();
            } catch (ValidationException $e) {
                // Expected
            }
        }

        // Assert - 6th attempt should be rate limited
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Too many login attempts');
        $request->authenticate();
    }
}
