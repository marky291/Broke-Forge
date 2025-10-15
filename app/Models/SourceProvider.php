<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a connected source provider (GitHub, GitLab, etc.) for a user.
 *
 * Source providers allow users to connect their Git hosting accounts via OAuth,
 * enabling features like automatic webhook management and repository access.
 */
class SourceProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'username',
        'email',
        'access_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
    ];

    /**
     * Get the user that owns this source provider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is a GitHub provider.
     */
    public function isGitHub(): bool
    {
        return $this->provider === 'github';
    }

    /**
     * Check if this is a GitLab provider.
     */
    public function isGitLab(): bool
    {
        return $this->provider === 'gitlab';
    }

    /**
     * Check if this is a Bitbucket provider.
     */
    public function isBitbucket(): bool
    {
        return $this->provider === 'bitbucket';
    }
}
