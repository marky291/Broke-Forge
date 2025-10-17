<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\ServerFileIndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ServerFileIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with valid path.
     */
    public function test_validation_passes_with_valid_path(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/html',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when path is not provided.
     */
    public function test_validation_passes_when_path_is_not_provided(): void
    {
        // Arrange
        $data = [];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when path is null.
     */
    public function test_validation_passes_when_path_is_null(): void
    {
        // Arrange
        $data = [
            'path' => null,
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when path is empty string.
     */
    public function test_validation_passes_when_path_is_empty_string(): void
    {
        // Arrange
        $data = [
            'path' => '',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with various valid directory paths.
     */
    public function test_validation_passes_with_various_valid_directory_paths(): void
    {
        // Arrange
        $request = new ServerFileIndexRequest;

        $validPaths = [
            '/var/www/html',
            '/home/user/files',
            '/etc/nginx',
            'storage/logs',
            'public/uploads',
            '/tmp',
            '/',
        ];

        foreach ($validPaths as $path) {
            $data = ['path' => $path];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Path '{$path}' should be valid");
        }
    }

    /**
     * Test validation passes with maximum valid path length.
     */
    public function test_validation_passes_with_maximum_valid_path_length(): void
    {
        // Arrange
        $data = [
            'path' => str_repeat('a', 4096),
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when path exceeds max length.
     */
    public function test_validation_fails_when_path_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'path' => str_repeat('a', 4097),
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('path', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with path containing spaces.
     */
    public function test_validation_passes_with_path_containing_spaces(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/My Documents',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with path containing special characters.
     */
    public function test_validation_passes_with_path_containing_special_characters(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/html/project-2024_backup',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with relative path.
     */
    public function test_validation_passes_with_relative_path(): void
    {
        // Arrange
        $data = [
            'path' => '../public',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with hidden directory path.
     */
    public function test_validation_passes_with_hidden_directory_path(): void
    {
        // Arrange
        $data = [
            'path' => '/home/user/.config',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with common directory paths.
     */
    public function test_validation_passes_with_common_directory_paths(): void
    {
        // Arrange
        $request = new ServerFileIndexRequest;

        $directoryPaths = [
            '/var/www/html/storage',
            '/home/user/backups',
            '/etc/nginx/sites-available',
            '/var/log',
            '/opt/app',
        ];

        foreach ($directoryPaths as $path) {
            $data = ['path' => $path];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Path '{$path}' should be valid");
        }
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new ServerFileIndexRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test validation passes with deep directory structure.
     */
    public function test_validation_passes_with_deep_directory_structure(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/html/storage/app/public/uploads/2024/01/15',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with root directory.
     */
    public function test_validation_passes_with_root_directory(): void
    {
        // Arrange
        $data = [
            'path' => '/',
        ];

        $request = new ServerFileIndexRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }
}
