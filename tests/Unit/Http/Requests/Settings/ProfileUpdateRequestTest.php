<?php

namespace Tests\Unit\Http\Requests\Settings;

use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProfileUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $user = User::factory()->create();

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when name is missing.
     */
    public function test_validation_fails_when_name_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();

        $data = [
            'email' => 'john@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when name exceeds max length.
     */
    public function test_validation_fails_when_name_exceeds_max_length(): void
    {
        // Arrange
        $user = User::factory()->create();

        $data = [
            'name' => str_repeat('a', 256),
            'email' => 'john@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with maximum valid name length.
     */
    public function test_validation_passes_with_maximum_valid_name_length(): void
    {
        // Arrange
        $user = User::factory()->create();

        $data = [
            'name' => str_repeat('a', 255),
            'email' => 'john@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

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
        $user = User::factory()->create();

        $data = [
            'name' => 'John Doe',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

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
        $user = User::factory()->create();

        $data = [
            'name' => 'John Doe',
            'email' => 'not-an-email',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when email exceeds max length.
     */
    public function test_validation_fails_when_email_exceeds_max_length(): void
    {
        // Arrange
        $user = User::factory()->create();

        $data = [
            'name' => 'John Doe',
            'email' => str_repeat('a', 244).'@example.com', // 256 chars total
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

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
        $user = User::factory()->create();
        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            'user_name@subdomain.example.com',
        ];

        foreach ($validEmails as $email) {
            $data = [
                'name' => 'John Doe',
                'email' => $email,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Email '{$email}' should be valid");
        }
    }

    /**
     * Test validation fails when email is not lowercase.
     */
    public function test_validation_fails_when_email_is_not_lowercase(): void
    {
        // Arrange
        $user = User::factory()->create();

        $data = [
            'name' => 'John Doe',
            'email' => 'JOHN@EXAMPLE.COM',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when email is taken by another user.
     */
    public function test_validation_fails_when_email_is_taken_by_another_user(): void
    {
        // Arrange
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $currentUser = User::factory()->create([
            'email' => 'current@example.com',
        ]);

        $data = [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $currentUser);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when user keeps their own email.
     */
    public function test_validation_passes_when_user_keeps_their_own_email(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when email is unique.
     */
    public function test_validation_passes_when_email_is_unique(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        $data = [
            'name' => 'John Doe',
            'email' => 'new@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with realistic profile update.
     */
    public function test_validation_passes_with_realistic_profile_update(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $data = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when only updating name.
     */
    public function test_validation_passes_when_only_updating_name(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'john@example.com',
        ]);

        $data = [
            'name' => 'New Name',
            'email' => 'john@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when only updating email.
     */
    public function test_validation_passes_when_only_updating_email(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'old@example.com',
        ]);

        $data = [
            'name' => 'John Doe',
            'email' => 'new@example.com',
        ];

        $request = new ProfileUpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }
}
