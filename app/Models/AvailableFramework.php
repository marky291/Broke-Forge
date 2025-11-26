<?php

namespace App\Models;

use App\Packages\Services\Sites\Framework\GenericPhp\GenericPhpInstallerJob;
use App\Packages\Services\Sites\Framework\Laravel\LaravelInstallerJob;
use App\Packages\Services\Sites\Framework\StaticHtml\StaticHtmlInstallerJob;
use App\Packages\Services\Sites\Framework\WordPress\WordPressInstallerJob;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Framework slug constants.
     */
    public const LARAVEL = 'laravel';

    public const WORDPRESS = 'wordpress';

    public const GENERIC_PHP = 'generic-php';

    public const STATIC_HTML = 'static-html';

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

    /**
     * Get the installer job class for this framework.
     *
     * @return class-string
     */
    public function getInstallerClass(): string
    {
        return match ($this->slug) {
            self::LARAVEL => LaravelInstallerJob::class,
            self::WORDPRESS => WordPressInstallerJob::class,
            self::GENERIC_PHP => GenericPhpInstallerJob::class,
            self::STATIC_HTML => StaticHtmlInstallerJob::class,
            default => throw new \RuntimeException("Unknown framework: {$this->slug}"),
        };
    }

    /**
     * Check if this framework requires a Git repository.
     *
     * WordPress sites don't require Git as they are installed via WP-CLI.
     */
    public function requiresGitRepository(): bool
    {
        return $this->slug !== self::WORDPRESS;
    }

    /**
     * Check if this framework requires a PHP version.
     *
     * Static HTML sites don't require PHP.
     */
    public function requiresPhpVersion(): bool
    {
        return $this->slug !== self::STATIC_HTML;
    }

    /**
     * Find a framework by its slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Scope a query to a specific slug.
     */
    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
