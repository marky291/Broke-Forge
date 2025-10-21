<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\InstallSiteGitRepositoryRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InstallSiteGitRepositoryRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => 'laravel/laravel',
            'branch' => 'main',
            'document_root' => '/public',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with minimal required data.
     */
    public function test_validation_passes_with_minimal_required_data(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => 'owner/repo',
            'branch' => 'main',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when provider is missing.
     */
    public function test_validation_fails_when_provider_is_missing(): void
    {
        // Arrange
        $data = [
            'repository' => 'owner/repo',
            'branch' => 'main',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('provider', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when provider is not github.
     */
    public function test_validation_fails_when_provider_is_not_github(): void
    {
        // Arrange
        $data = [
            'provider' => 'gitlab',
            'repository' => 'owner/repo',
            'branch' => 'main',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('provider', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when repository is missing.
     */
    public function test_validation_fails_when_repository_is_missing(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'branch' => 'main',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('repository', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with owner/repo format.
     */
    public function test_validation_passes_with_owner_repo_format(): void
    {
        // Arrange
        $repositories = [
            'laravel/laravel',
            'symfony/symfony',
            'owner-name/repo-name',
            'owner_name/repo_name',
            'owner.name/repo.name',
        ];

        foreach ($repositories as $repository) {
            $data = [
                'provider' => 'github',
                'repository' => $repository,
                'branch' => 'main',
            ];

            $request = new InstallSiteGitRepositoryRequest;

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Repository '{$repository}' should be valid");
        }
    }

    /**
     * Test validation passes with git SSH URL formats.
     */
    public function test_validation_passes_with_git_ssh_url_formats(): void
    {
        // Arrange
        $repositories = [
            'git@github.com:owner/repo.git',
            'git@github.com:owner/repo-name.git',
            'ssh://git@github.com/owner/repo.git',
            'https://github.com/owner/repo.git',
            'https://github.com/owner/repo',
        ];

        foreach ($repositories as $repository) {
            $data = [
                'provider' => 'github',
                'repository' => $repository,
                'branch' => 'main',
            ];

            $request = new InstallSiteGitRepositoryRequest;

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Repository '{$repository}' should be valid");
        }
    }

    /**
     * Test validation fails when repository exceeds max length.
     */
    public function test_validation_fails_when_repository_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => str_repeat('a', 256),
            'branch' => 'main',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('repository', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when branch is missing.
     */
    public function test_validation_fails_when_branch_is_missing(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => 'owner/repo',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('branch', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid branch names.
     */
    public function test_validation_passes_with_valid_branch_names(): void
    {
        // Arrange
        $branches = [
            'main',
            'develop',
            'feature/new-feature',
            'release/1.0.0',
            'hotfix/bug-fix',
            'feature_branch',
            'feature.branch',
            'main-branch',
        ];

        foreach ($branches as $branch) {
            $data = [
                'provider' => 'github',
                'repository' => 'owner/repo',
                'branch' => $branch,
            ];

            $request = new InstallSiteGitRepositoryRequest;

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Branch '{$branch}' should be valid");
        }
    }

    /**
     * Test validation fails with invalid branch names.
     */
    public function test_validation_fails_with_invalid_branch_names(): void
    {
        // Arrange
        $branches = [
            'branch with spaces',
            'branch@special',
            'branch#hash',
            'branch!exclaim',
        ];

        foreach ($branches as $branch) {
            $data = [
                'provider' => 'github',
                'repository' => 'owner/repo',
                'branch' => $branch,
            ];

            $request = new InstallSiteGitRepositoryRequest;

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertTrue($validator->fails(), "Branch '{$branch}' should be invalid");
            $this->assertArrayHasKey('branch', $validator->errors()->toArray());
        }
    }

    /**
     * Test validation fails when branch exceeds max length.
     */
    public function test_validation_fails_when_branch_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => 'owner/repo',
            'branch' => str_repeat('a', 256),
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('branch', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when document_root is not provided.
     */
    public function test_validation_passes_when_document_root_is_not_provided(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => 'owner/repo',
            'branch' => 'main',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with valid document_root paths.
     */
    public function test_validation_passes_with_valid_document_root_paths(): void
    {
        // Arrange
        $paths = [
            '/public',
            '/dist',
            '/build',
            'public',
            'dist/public',
            '/var/www/public',
        ];

        foreach ($paths as $path) {
            $data = [
                'provider' => 'github',
                'repository' => 'owner/repo',
                'branch' => 'main',
                'document_root' => $path,
            ];

            $request = new InstallSiteGitRepositoryRequest;

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Document root '{$path}' should be valid");
        }
    }

    /**
     * Test validation fails when document_root exceeds max length.
     */
    public function test_validation_fails_when_document_root_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => 'owner/repo',
            'branch' => 'main',
            'document_root' => str_repeat('a', 256),
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('document_root', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with realistic repository data.
     */
    public function test_validation_passes_with_realistic_repository_data(): void
    {
        // Arrange
        $data = [
            'provider' => 'github',
            'repository' => 'laravel/laravel',
            'branch' => '11.x',
            'document_root' => '/public',
        ];

        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new InstallSiteGitRepositoryRequest;

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
        $request = new InstallSiteGitRepositoryRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('provider.in', $messages);
        $this->assertArrayHasKey('repository.regex', $messages);
        $this->assertArrayHasKey('branch.regex', $messages);
        $this->assertArrayHasKey('document_root.regex', $messages);
    }
}
