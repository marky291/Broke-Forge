<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\ServerFileDownloadRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ServerFileDownloadRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with valid path.
     */
    public function test_validation_passes_with_valid_path(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/html/file.txt',
        ];

        $request = new ServerFileDownloadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when path is missing.
     */
    public function test_validation_fails_when_path_is_missing(): void
    {
        // Arrange
        $data = [];

        $request = new ServerFileDownloadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('path', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when path is empty string.
     */
    public function test_validation_fails_when_path_is_empty_string(): void
    {
        // Arrange
        $data = [
            'path' => '',
        ];

        $request = new ServerFileDownloadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('path', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various valid path formats.
     */
    public function test_validation_passes_with_various_valid_path_formats(): void
    {
        // Arrange
        $request = new ServerFileDownloadRequest;

        $validPaths = [
            '/var/www/html/index.php',
            '/home/user/.env',
            '/etc/nginx/nginx.conf',
            'logs/laravel.log',
            'storage/app/public/file.pdf',
            '/var/log/nginx/access.log',
            '/tmp/backup.zip',
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

        $request = new ServerFileDownloadRequest;

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

        $request = new ServerFileDownloadRequest;

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
            'path' => '/var/www/My Documents/file.txt',
        ];

        $request = new ServerFileDownloadRequest;

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
            'path' => '/var/www/html/file-name_2024.backup.tar.gz',
        ];

        $request = new ServerFileDownloadRequest;

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
            'path' => '../public/index.php',
        ];

        $request = new ServerFileDownloadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with hidden file path.
     */
    public function test_validation_passes_with_hidden_file_path(): void
    {
        // Arrange
        $data = [
            'path' => '/home/user/.bashrc',
        ];

        $request = new ServerFileDownloadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with path to common file types.
     */
    public function test_validation_passes_with_path_to_common_file_types(): void
    {
        // Arrange
        $request = new ServerFileDownloadRequest;

        $filePaths = [
            '/var/www/html/database.sql',
            '/home/user/backup.tar.gz',
            '/etc/ssl/cert.pem',
            '/var/log/app.log',
            '/home/user/document.pdf',
            '/var/www/html/.env',
        ];

        foreach ($filePaths as $path) {
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
        $request = new ServerFileDownloadRequest;

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
            'path' => '/var/www/html/storage/app/public/uploads/2024/01/15/file.txt',
        ];

        $request = new ServerFileDownloadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }
}
