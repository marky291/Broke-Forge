<?php

namespace App\Packages\Credential;

/**
 * SSH Key Generator
 *
 * Service for generating RSA SSH key pairs.
 * Extracted from ServerCredential to separate key generation from persistence.
 */
class SshKeyGenerator
{
    /**
     * Generate a new RSA 4096-bit SSH key pair.
     *
     * @param  int  $serverId  Server ID for key comment
     * @param  string  $user  SSH user for key comment ('root' or 'brokeforge')
     * @return array{private_key: string, public_key: string} The generated key pair
     *
     * @throws \RuntimeException If key generation fails
     */
    public function generate(int $serverId, string $user): array
    {
        $tempDir = sys_get_temp_dir();
        $keyName = sprintf('server_%d_%s_%d', $serverId, $user, time());
        $keyPath = $tempDir.'/'.$keyName;

        // Generate RSA 4096-bit key pair
        $command = sprintf(
            'ssh-keygen -t rsa -b 4096 -f %s -N "" -C "%s@server-%d"',
            escapeshellarg($keyPath),
            $user,
            $serverId
        );

        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new \RuntimeException("Failed to generate SSH key pair for {$user} credential");
        }

        // Read generated keys
        $privateKey = file_get_contents($keyPath);
        $publicKey = file_get_contents($keyPath.'.pub');

        // Clean up temporary files
        @unlink($keyPath);
        @unlink($keyPath.'.pub');

        if ($privateKey === false || $publicKey === false) {
            throw new \RuntimeException('Failed to read generated SSH keys');
        }

        return [
            'private_key' => $privateKey, // Do NOT trim - SSH keys require trailing newline
            'public_key' => trim($publicKey),
        ];
    }
}
