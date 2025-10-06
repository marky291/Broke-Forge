<?php

namespace App\Packages\Services\PHP\Services;

class PhpConfigurationService
{
    /**
     * Get available PHP versions (Ubuntu 24.04 compatible)
     */
    public static function getAvailableVersions(): array
    {
        return [
            '8.1' => 'PHP 8.1',
            '8.2' => 'PHP 8.2',
            '8.3' => 'PHP 8.3',
            '8.4' => 'PHP 8.4',
        ];
    }

    /**
     * Get available PHP extensions with descriptions
     */
    public static function getAvailableExtensions(): array
    {
        return [
            'bcmath' => 'BCMath - Arbitrary precision mathematics',
            'curl' => 'cURL - Client URL Library',
            'gd' => 'GD - Image processing',
            'intl' => 'Intl - Internationalization',
            'mbstring' => 'Multibyte String - String handling',
            'mysql' => 'MySQL - Database connectivity',
            'opcache' => 'OPcache - Bytecode cache',
            'pdo' => 'PDO - PHP Data Objects',
            'redis' => 'Redis - In-memory data structure store',
            'xml' => 'XML - XML parsing',
            'zip' => 'Zip - Archive handling',
            'imagick' => 'ImageMagick - Advanced image processing',
            'gmp' => 'GMP - GNU Multiple Precision',
            'soap' => 'SOAP - Simple Object Access Protocol',
            'xdebug' => 'Xdebug - Debugging and profiling',
            'apcu' => 'APCu - User cache',
            'memcached' => 'Memcached - Distributed memory caching',
            'mongodb' => 'MongoDB - NoSQL database driver',
            'pgsql' => 'PostgreSQL - Database connectivity',
            'sqlite3' => 'SQLite3 - Lightweight database',
        ];
    }

    /**
     * Get default PHP configuration settings
     */
    public static function getDefaultSettings(): array
    {
        return [
            'memory_limit' => '256M',
            'max_execution_time' => 30,
            'upload_max_filesize' => '2M',
            'post_max_size' => '8M',
            'max_input_vars' => 1000,
            'max_file_uploads' => 20,
        ];
    }

    /**
     * Get validation rules for PHP installation
     */
    public static function getValidationRules(): array
    {
        $versions = array_keys(self::getAvailableVersions());
        $extensions = array_keys(self::getAvailableExtensions());

        return [
            'version' => 'required|in:'.implode(',', $versions),
            'extensions' => 'array',
            'extensions.*' => 'string|in:'.implode(',', $extensions),
            'memory_limit' => 'nullable|string|regex:/^\d+[KMG]$/i',
            'max_execution_time' => 'nullable|integer|min:0|max:300',
            'upload_max_filesize' => 'nullable|string|regex:/^\d+[KMG]$/i',
            'post_max_size' => 'nullable|string|regex:/^\d+[KMG]$/i',
            'is_cli_default' => 'nullable|boolean',
        ];
    }
}
