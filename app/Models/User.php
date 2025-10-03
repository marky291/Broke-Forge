<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Retrieve all servers provisioned by the user.
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Get all source providers connected by the user.
     */
    public function sourceProviders(): HasMany
    {
        return $this->hasMany(SourceProvider::class);
    }

    /**
     * Get the user's GitHub provider if connected.
     */
    public function githubProvider(): ?SourceProvider
    {
        return $this->sourceProviders()
            ->where('provider', 'github')
            ->first();
    }

    /**
     * Check if user has GitHub connected.
     */
    public function hasGitHubConnected(): bool
    {
        return $this->githubProvider() !== null;
    }
}
