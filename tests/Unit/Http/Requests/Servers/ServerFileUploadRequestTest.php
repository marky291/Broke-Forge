<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\ServerFileUploadRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ServerFileUploadRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with file and path.
     */
    public function test_validation_passes_with_file_and_path(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/html',
            'file' => UploadedFile::fake()->create('test.txt', 100),
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with file only (path nullable).
     */
    public function test_validation_passes_with_file_only(): void
    {
        // Arrange
        $data = [
            'file' => UploadedFile::fake()->create('test.txt', 100),
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when file is missing.
     */
    public function test_validation_fails_when_file_is_missing(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/html',
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various valid path formats.
     */
    public function test_validation_passes_with_various_valid_path_formats(): void
    {
        // Arrange
        $request = new ServerFileUploadRequest;

        $validPaths = [
            '/var/www/html',
            '/home/user/files',
            'uploads/',
            'documents/2024',
            '/path/to/some/deep/directory/structure',
            '/var/www/html/public',
        ];

        foreach ($validPaths as $path) {
            $data = [
                'path' => $path,
                'file' => UploadedFile::fake()->create('test.txt', 100),
            ];

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
            'file' => UploadedFile::fake()->create('test.txt', 100),
        ];

        $request = new ServerFileUploadRequest;

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
            'file' => UploadedFile::fake()->create('test.txt', 100),
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('path', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with small file.
     */
    public function test_validation_passes_with_small_file(): void
    {
        // Arrange
        $data = [
            'file' => UploadedFile::fake()->create('small.txt', 1), // 1 KB
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with maximum valid file size.
     */
    public function test_validation_passes_with_maximum_valid_file_size(): void
    {
        // Arrange
        $data = [
            'file' => UploadedFile::fake()->create('large.pdf', 51200), // 50 MB
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when file exceeds max size.
     */
    public function test_validation_fails_when_file_exceeds_max_size(): void
    {
        // Arrange
        $data = [
            'file' => UploadedFile::fake()->create('toolarge.zip', 51201), // 50 MB + 1 KB
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various file types.
     */
    public function test_validation_passes_with_various_file_types(): void
    {
        // Arrange
        $request = new ServerFileUploadRequest;

        $fileTypes = [
            'document.txt',
            'image.jpg',
            'archive.zip',
            'script.sh',
            'config.json',
            'data.csv',
            'page.html',
        ];

        foreach ($fileTypes as $fileName) {
            $data = [
                'file' => UploadedFile::fake()->create($fileName, 100),
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "File '{$fileName}' should be valid");
        }
    }

    /**
     * Test validation passes when path is null.
     */
    public function test_validation_passes_when_path_is_null(): void
    {
        // Arrange
        $data = [
            'path' => null,
            'file' => UploadedFile::fake()->create('test.txt', 100),
        ];

        $request = new ServerFileUploadRequest;

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
            'file' => UploadedFile::fake()->create('test.txt', 100),
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with realistic upload scenario.
     */
    public function test_validation_passes_with_realistic_upload_scenario(): void
    {
        // Arrange
        $data = [
            'path' => '/var/www/html/public/uploads',
            'file' => UploadedFile::fake()->image('photo.jpg', 1920, 1080)->size(2048), // 2 MB image
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new ServerFileUploadRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test validation fails with non-file data.
     */
    public function test_validation_fails_with_non_file_data(): void
    {
        // Arrange
        $data = [
            'file' => 'not-a-file',
        ];

        $request = new ServerFileUploadRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }
}
