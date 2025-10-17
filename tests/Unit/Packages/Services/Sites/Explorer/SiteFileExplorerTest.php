<?php

namespace Tests\Unit\Packages\Services\Sites\Explorer;

use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use App\Packages\Services\Sites\Explorer\Exceptions\ServerFileExplorerException;
use App\Packages\Services\Sites\Explorer\SiteFileExplorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteFileExplorerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test normalizeRelativePath returns empty string for null input.
     */
    public function test_normalize_relative_path_returns_empty_string_for_null_input(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, null);

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * Test normalizeRelativePath returns empty string for empty input.
     */
    public function test_normalize_relative_path_returns_empty_string_for_empty_input(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, '');

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * Test normalizeRelativePath strips leading and trailing slashes.
     */
    public function test_normalize_relative_path_strips_leading_and_trailing_slashes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertEquals('foo/bar', $method->invoke($explorer, '/foo/bar/'));
        $this->assertEquals('foo/bar', $method->invoke($explorer, 'foo/bar'));
        $this->assertEquals('foo/bar', $method->invoke($explorer, '///foo/bar///'));
    }

    /**
     * Test normalizeRelativePath removes null bytes.
     */
    public function test_normalize_relative_path_removes_null_bytes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, "foo\x00bar");

        // Assert
        $this->assertEquals('foobar', $result);
    }

    /**
     * Test normalizeRelativePath throws exception for path traversal attempts.
     */
    public function test_normalize_relative_path_throws_exception_for_path_traversal_attempts(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Assert
        $this->expectException(ServerFileExplorerException::class);
        $this->expectExceptionMessage('Path traversal is not allowed');

        // Act
        $method->invoke($explorer, 'foo/../bar');
    }

    /**
     * Test normalizeRelativePath throws exception for direct parent directory reference.
     */
    public function test_normalize_relative_path_throws_exception_for_direct_parent_directory_reference(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Assert
        $this->expectException(ServerFileExplorerException::class);
        $this->expectExceptionMessage('Path traversal is not allowed');

        // Act
        $method->invoke($explorer, '..');
    }

    /**
     * Test normalizeRelativePath removes dot segments.
     */
    public function test_normalize_relative_path_removes_dot_segments(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertEquals('foo/bar', $method->invoke($explorer, 'foo/./bar'));
        $this->assertEquals('foo/bar', $method->invoke($explorer, './foo/./bar/.'));
    }

    /**
     * Test normalizeRelativePath removes empty segments.
     */
    public function test_normalize_relative_path_removes_empty_segments(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertEquals('foo/bar', $method->invoke($explorer, 'foo//bar'));
        $this->assertEquals('foo/bar', $method->invoke($explorer, 'foo///bar'));
    }

    /**
     * Test normalizeRelativePath handles valid nested paths.
     */
    public function test_normalize_relative_path_handles_valid_nested_paths(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('normalizeRelativePath');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertEquals('foo/bar/baz', $method->invoke($explorer, 'foo/bar/baz'));
        $this->assertEquals('a/b/c/d/e', $method->invoke($explorer, 'a/b/c/d/e'));
    }

    /**
     * Test sanitizeFilename removes null bytes.
     */
    public function test_sanitize_filename_removes_null_bytes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, "test\x00file.txt");

        // Assert
        $this->assertEquals('testfile.txt', $result);
    }

    /**
     * Test sanitizeFilename removes forward slashes.
     */
    public function test_sanitize_filename_removes_forward_slashes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, 'test/file.txt');

        // Assert
        $this->assertEquals('testfile.txt', $result);
    }

    /**
     * Test sanitizeFilename removes backslashes.
     */
    public function test_sanitize_filename_removes_backslashes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, 'test\\file.txt');

        // Assert
        $this->assertEquals('testfile.txt', $result);
    }

    /**
     * Test sanitizeFilename collapses consecutive whitespace.
     */
    public function test_sanitize_filename_collapses_consecutive_whitespace(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, 'test    file.txt');

        // Assert
        $this->assertEquals('test file.txt', $result);
    }

    /**
     * Test sanitizeFilename limits filename to 200 characters.
     */
    public function test_sanitize_filename_limits_filename_to_200_characters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        $longFilename = str_repeat('a', 250).'.txt';

        // Act
        $result = $method->invoke($explorer, $longFilename);

        // Assert
        $this->assertLessThanOrEqual(200, strlen($result));
    }

    /**
     * Test sanitizeFilename returns empty string for empty input.
     */
    public function test_sanitize_filename_returns_empty_string_for_empty_input(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, '');

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * Test sanitizeFilename handles valid filename.
     */
    public function test_sanitize_filename_handles_valid_filename(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, 'valid-filename.txt');

        // Assert
        $this->assertEquals('valid-filename.txt', $result);
    }

    /**
     * Test basePath returns dirname of document_root.
     */
    public function test_base_path_returns_dirname_of_document_root(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('basePath');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer);

        // Assert
        $this->assertEquals('/home/brokeforge/example.com', $result);
    }

    /**
     * Test basePath works with different document_root paths.
     */
    public function test_base_path_works_with_different_document_root_paths(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/var/www/html/site/public_html',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('basePath');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer);

        // Assert
        $this->assertEquals('/var/www/html/site', $result);
    }

    /**
     * Test sanitizeFilename handles filenames with only invalid characters.
     */
    public function test_sanitize_filename_handles_filenames_with_only_invalid_characters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $explorer = new SiteFileExplorer($site);

        $reflection = new \ReflectionClass($explorer);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($explorer, '///\\\\\\');

        // Assert
        $this->assertEquals('', $result);
    }
}
