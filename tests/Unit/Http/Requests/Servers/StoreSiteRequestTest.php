<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\StoreSiteRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreSiteRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => true,
            'git_repository' => 'owner/repository',
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when domain is missing.
     */
    public function test_validation_fails_when_domain_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'php_version' => '8.3',
            'ssl' => true,
            'git_repository' => 'owner/repo',
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('domain', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid domain formats.
     */
    public function test_validation_passes_with_valid_domain_formats(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $validDomains = [
            'example.com',
            'sub.example.com',
            'deep.sub.example.com',
            'my-site.com',
            'site123.com',
            'example.co.uk',
            'myproject',
            'my-project',
            'test-app',
            'app123',
        ];

        foreach ($validDomains as $domain) {
            $data = [
                'domain' => $domain,
                'php_version' => '8.3',
                'ssl' => true,
                'git_repository' => 'owner/repo',
                'git_branch' => 'main',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Domain {$domain} should be valid");
        }
    }

    /**
     * Test validation fails with invalid domain formats.
     */
    public function test_validation_fails_with_invalid_domain_formats(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $invalidDomains = [
            'example..com',
            '-example.com',
            'example-.com',
            'example.com-',
            'example .com',
            '-myproject',
            'myproject-',
            'my project',
        ];

        foreach ($invalidDomains as $domain) {
            $data = [
                'domain' => $domain,
                'php_version' => '8.3',
                'ssl' => true,
                'git_repository' => 'owner/repo',
                'git_branch' => 'main',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertTrue($validator->fails(), "Domain {$domain} should be invalid");
            $this->assertArrayHasKey('domain', $validator->errors()->toArray());
        }
    }

    /**
     * Test validation fails when domain is duplicate on same server.
     */
    public function test_validation_fails_when_domain_is_duplicate_on_same_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
        ]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => true,
            'git_repository' => 'owner/repo',
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('domain', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when domain exists on different server.
     */
    public function test_validation_passes_when_domain_exists_on_different_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server1->id,
            'domain' => 'example.com',
        ]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server2)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => true,
            'git_repository' => 'owner/repo',
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when php_version is missing.
     */
    public function test_validation_fails_when_php_version_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'ssl' => true,
            'git_repository' => 'owner/repo',
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('php_version', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with all valid php_versions.
     */
    public function test_validation_passes_with_all_valid_php_versions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $validVersions = ['7.4', '8.0', '8.1', '8.2', '8.3'];
        $counter = 10;

        foreach ($validVersions as $version) {
            $data = [
                'domain' => "example{$counter}.com",
                'php_version' => $version,
                'ssl' => true,
                'git_repository' => 'owner/repo',
                'git_branch' => 'main',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "PHP version {$version} should be valid");

            $counter++;
        }
    }

    /**
     * Test validation fails with invalid php_version.
     */
    public function test_validation_fails_with_invalid_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'php_version' => '8.4',
            'ssl' => true,
            'git_repository' => 'owner/repo',
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('php_version', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when ssl is missing.
     */
    public function test_validation_fails_when_ssl_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'git_repository' => 'owner/repo',
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ssl', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with ssl as true or false.
     */
    public function test_validation_passes_with_ssl_as_true_or_false(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        foreach ([true, false] as $sslValue) {
            $data = [
                'domain' => $sslValue ? 'ssl-enabled.com' : 'ssl-disabled.com',
                'php_version' => '8.3',
                'ssl' => $sslValue,
                'git_repository' => 'owner/repo',
                'git_branch' => 'main',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails());
        }
    }

    /**
     * Test validation fails when git_repository is missing.
     */
    public function test_validation_fails_when_git_repository_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => true,
            'git_branch' => 'main',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('git_repository', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid git_repository formats.
     */
    public function test_validation_passes_with_valid_git_repository_formats(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $validRepos = [
            'owner/repo',
            'user123/project456',
            'my-org/my-project',
            'org_name/repo_name',
            'owner/repo.name',
        ];

        $counter = 0;
        foreach ($validRepos as $repo) {
            $data = [
                'domain' => "example{$counter}.com",
                'php_version' => '8.3',
                'ssl' => true,
                'git_repository' => $repo,
                'git_branch' => 'main',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Git repository {$repo} should be valid");

            $counter++;
        }
    }

    /**
     * Test validation fails with invalid git_repository formats.
     */
    public function test_validation_fails_with_invalid_git_repository_formats(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $invalidRepos = [
            'owner',
            'owner/',
            '/repo',
            'owner/repo/extra',
            'owner@repo',
            'owner repo',
        ];

        $counter = 0;
        foreach ($invalidRepos as $repo) {
            $data = [
                'domain' => "example{$counter}.com",
                'php_version' => '8.3',
                'ssl' => true,
                'git_repository' => $repo,
                'git_branch' => 'main',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertTrue($validator->fails(), "Git repository {$repo} should be invalid");
            $this->assertArrayHasKey('git_repository', $validator->errors()->toArray());

            $counter++;
        }
    }

    /**
     * Test validation fails when git_branch is missing.
     */
    public function test_validation_fails_when_git_branch_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => true,
            'git_repository' => 'owner/repo',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('git_branch', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid git_branch formats.
     */
    public function test_validation_passes_with_valid_git_branch_formats(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $validBranches = [
            'main',
            'master',
            'develop',
            'feature/new-feature',
            'bugfix/fix-123',
            'release/v1.0.0',
            'feature_branch',
            'branch.name',
        ];

        $counter = 0;
        foreach ($validBranches as $branch) {
            $data = [
                'domain' => "example{$counter}.com",
                'php_version' => '8.3',
                'ssl' => true,
                'git_repository' => 'owner/repo',
                'git_branch' => $branch,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Git branch {$branch} should be valid");

            $counter++;
        }
    }

    /**
     * Test validation fails with invalid git_branch formats.
     */
    public function test_validation_fails_with_invalid_git_branch_formats(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $request = new StoreSiteRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $invalidBranches = [
            'branch name',
            'branch@name',
            'branch#name',
        ];

        $counter = 0;
        foreach ($invalidBranches as $branch) {
            $data = [
                'domain' => "example{$counter}.com",
                'php_version' => '8.3',
                'ssl' => true,
                'git_repository' => 'owner/repo',
                'git_branch' => $branch,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertTrue($validator->fails(), "Git branch {$branch} should be invalid");
            $this->assertArrayHasKey('git_branch', $validator->errors()->toArray());

            $counter++;
        }
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new StoreSiteRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test custom error messages are defined.
     */
    public function test_custom_error_logs_are_defined(): void
    {
        // Arrange
        $request = new StoreSiteRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('domain.required', $messages);
        $this->assertArrayHasKey('domain.regex', $messages);
        $this->assertArrayHasKey('domain.unique', $messages);
        $this->assertArrayHasKey('php_version.required', $messages);
        $this->assertArrayHasKey('php_version.in', $messages);
        $this->assertArrayHasKey('ssl.required', $messages);
        $this->assertArrayHasKey('git_repository.required', $messages);
        $this->assertArrayHasKey('git_repository.regex', $messages);
        $this->assertArrayHasKey('git_branch.required', $messages);
        $this->assertArrayHasKey('git_branch.regex', $messages);
    }
}
