<?php

namespace App\Services;

class PhpConfigurationService
{
    public function getAvailableVersions(): array
    {
        return [
            '8.4' => [
                'name' => 'PHP 8.4',
                'description' => 'Latest stable release with modern features',
                'status' => 'stable',
                'default_modules' => $this->getDefaultModules(),
                'recommended' => true,
            ],
            '8.3' => [
                'name' => 'PHP 8.3',
                'description' => 'Long Term Support (LTS) version',
                'status' => 'lts',
                'default_modules' => $this->getDefaultModules(),
                'recommended' => true,
            ],
            '8.2' => [
                'name' => 'PHP 8.2',
                'description' => 'Stable release with performance improvements',
                'status' => 'stable',
                'default_modules' => $this->getDefaultModules(),
                'recommended' => false,
            ],
            '8.1' => [
                'name' => 'PHP 8.1',
                'description' => 'Legacy support version',
                'status' => 'legacy',
                'default_modules' => $this->getDefaultModules(),
                'recommended' => false,
            ],
        ];
    }

    public function getDefaultModules(): array
    {
        return [
            'bcmath',
            'ctype',
            'curl',
            'dom',
            'fileinfo',
            'gd',
            'intl',
            'json',
            'mbstring',
            'mysql',
            'opcache',
            'openssl',
            'pdo',
            'tokenizer',
            'xml',
            'zip',
        ];
    }

    public function getOptionalModules(): array
    {
        return [
            'redis' => 'Redis cache support',
            'memcached' => 'Memcached cache support',
            'imagick' => 'Advanced image processing',
            'xdebug' => 'Development debugging (not for production)',
            'mongodb' => 'MongoDB database support',
            'ldap' => 'LDAP authentication support',
        ];
    }

    public function getVersionConfiguration(string $version): array
    {
        $versions = $this->getAvailableVersions();

        return $versions[$version] ?? [];
    }

    public function isVersionSupported(string $version): bool
    {
        return array_key_exists($version, $this->getAvailableVersions());
    }

    public function getRecommendedVersion(): string
    {
        $versions = $this->getAvailableVersions();

        foreach ($versions as $version => $config) {
            if ($config['recommended'] && $config['status'] === 'stable') {
                return $version;
            }
        }

        return '8.3'; // Fallback to LTS
    }

    public function getFpmConfiguration(string $version): array
    {
        return [
            'pm' => 'dynamic',
            'pm.max_children' => 20,
            'pm.start_servers' => 2,
            'pm.min_spare_servers' => 1,
            'pm.max_spare_servers' => 3,
            'pm.process_idle_timeout' => '10s',
            'pm.max_requests' => 1000,
        ];
    }
}
