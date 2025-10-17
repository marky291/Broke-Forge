<?php

namespace Tests\Unit\Packages\Credential;

use App\Packages\Credential\SshKeyGenerator;
use Tests\TestCase;

class SshKeyGeneratorTest extends TestCase
{
    /**
     * Test generates valid SSH key pair for root user.
     */
    public function test_generates_valid_ssh_key_pair_for_root_user(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 123;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert
        $this->assertIsArray($keys);
        $this->assertArrayHasKey('private_key', $keys);
        $this->assertArrayHasKey('public_key', $keys);
        $this->assertNotEmpty($keys['private_key']);
        $this->assertNotEmpty($keys['public_key']);
    }

    /**
     * Test generates valid SSH key pair for brokeforge user.
     */
    public function test_generates_valid_ssh_key_pair_for_brokeforge_user(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 456;
        $user = 'brokeforge';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert
        $this->assertIsArray($keys);
        $this->assertArrayHasKey('private_key', $keys);
        $this->assertArrayHasKey('public_key', $keys);
        $this->assertNotEmpty($keys['private_key']);
        $this->assertNotEmpty($keys['public_key']);
    }

    /**
     * Test private key has correct OpenSSH format.
     */
    public function test_private_key_has_correct_openssh_format(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 789;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert
        $this->assertStringContainsString('-----BEGIN OPENSSH PRIVATE KEY-----', $keys['private_key']);
        $this->assertStringContainsString('-----END OPENSSH PRIVATE KEY-----', $keys['private_key']);
    }

    /**
     * Test public key has correct SSH format.
     */
    public function test_public_key_has_correct_ssh_format(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 101;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert
        $this->assertStringStartsWith('ssh-rsa', $keys['public_key']);
    }

    /**
     * Test public key includes correct comment.
     */
    public function test_public_key_includes_correct_comment(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 202;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert - verify comment is user@server-{id}
        $this->assertStringContainsString("root@server-{$serverId}", $keys['public_key']);
    }

    /**
     * Test brokeforge user has correct comment in public key.
     */
    public function test_brokeforge_user_has_correct_comment_in_public_key(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 303;
        $user = 'brokeforge';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert
        $this->assertStringContainsString("brokeforge@server-{$serverId}", $keys['public_key']);
    }

    /**
     * Test private key preserves trailing newline.
     */
    public function test_private_key_preserves_trailing_newline(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 404;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert - SSH keys require trailing newline
        $this->assertStringEndsWith("\n", $keys['private_key']);
    }

    /**
     * Test public key is trimmed.
     */
    public function test_public_key_is_trimmed(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 505;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert - public key should be trimmed (no leading/trailing whitespace)
        $this->assertEquals(trim($keys['public_key']), $keys['public_key']);
    }

    /**
     * Test temporary key files are cleaned up after generation.
     */
    public function test_temporary_key_files_are_cleaned_up(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 606;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert - verify keys were generated
        $this->assertNotEmpty($keys['private_key']);
        $this->assertNotEmpty($keys['public_key']);

        // The temp files should have been cleaned up already
        $tempDir = sys_get_temp_dir();
        $pattern = sprintf('%s/server_%d_%s_*', $tempDir, $serverId, $user);
        $remainingFiles = glob($pattern);

        $this->assertEmpty($remainingFiles, 'Temporary key files should be cleaned up');
    }

    /**
     * Test generates unique keys for different servers.
     */
    public function test_generates_unique_keys_for_different_servers(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;

        // Act
        $keys1 = $generator->generate(1, 'root');
        $keys2 = $generator->generate(2, 'root');

        // Assert - keys should be different
        $this->assertNotEquals($keys1['private_key'], $keys2['private_key']);
        $this->assertNotEquals($keys1['public_key'], $keys2['public_key']);
    }

    /**
     * Test generates unique keys for different users on same server.
     */
    public function test_generates_unique_keys_for_different_users_on_same_server(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 707;

        // Act
        $rootKeys = $generator->generate($serverId, 'root');
        $brokeforgeKeys = $generator->generate($serverId, 'brokeforge');

        // Assert - keys should be different
        $this->assertNotEquals($rootKeys['private_key'], $brokeforgeKeys['private_key']);
        $this->assertNotEquals($rootKeys['public_key'], $brokeforgeKeys['public_key']);
    }

    /**
     * Test generates RSA 4096-bit keys.
     */
    public function test_generates_rsa_4096_bit_keys(): void
    {
        // Arrange
        $generator = new SshKeyGenerator;
        $serverId = 808;
        $user = 'root';

        // Act
        $keys = $generator->generate($serverId, $user);

        // Assert - verify key type and approximate size
        $this->assertStringStartsWith('ssh-rsa', $keys['public_key']);

        // RSA 4096-bit keys are quite large
        $this->assertGreaterThan(500, strlen($keys['private_key']));
        $this->assertGreaterThan(500, strlen($keys['public_key']));
    }
}
