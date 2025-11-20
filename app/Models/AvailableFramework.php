<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a framework type that can be used for sites.
 *
 * This model defines available frameworks (Laravel, WordPress, etc.)
 * with their environment file paths and dependency requirements.
 */
class AvailableFramework extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'public_directory',
        'env',
        'requirements',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'env' => 'array',
            'requirements' => 'array',
        ];
    }

    /**
     * Get all sites using this framework.
     */
    public function sites(): HasMany
    {
        return $this->hasMany(ServerSite::class);
    }

    /**
     * Check if this framework requires a database.
     */
    public function requiresDatabase(): bool
    {
        return $this->requirements['database'] ?? false;
    }

    /**
     * Check if this framework requires Redis.
     */
    public function requiresRedis(): bool
    {
        return $this->requirements['redis'] ?? false;
    }

    /**
     * Check if this framework requires Node.js/NPM.
     */
    public function requiresNodejs(): bool
    {
        return $this->requirements['nodejs'] ?? false;
    }

    /**
     * Check if this framework requires Composer.
     */
    public function requiresComposer(): bool
    {
        return $this->requirements['composer'] ?? false;
    }

    /**
     * Check if this framework supports environment file editing.
     */
    public function supportsEnv(): bool
    {
        return $this->env['supports'] ?? false;
    }

    /**
     * Get the environment file path for this framework.
     */
    public function getEnvFilePath(): ?string
    {
        return $this->env['file_path'] ?? null;
    }

    /**
     * Get the public directory path for this framework.
     *
     * Returns empty string for frameworks where root is public (like WordPress),
     * or '/public' for frameworks with a separate public subdirectory.
     */
    public function getPublicDirectory(): string
    {
        return $this->public_directory ?? '/public';
    }

    /**
     * Check if this framework uses a separate public subdirectory.
     */
    public function hasPublicSubdirectory(): bool
    {
        return $this->getPublicDirectory() !== '';
    }
}
