<?php

namespace Tests\Unit\Provision\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Provision\Sites\GitProvision;
use InvalidArgumentException;
use LogicException;
use ReflectionMethod;
use Tests\TestCase;

class GitProvisionTest extends TestCase
{
    public function test_it_builds_expected_commands_for_owner_repository(): void
    {
        $server = $this->makeServer();
        $site = $this->makeSite();

        $provision = new GitProvision($server);
        $provision->forSite($site)->setConfiguration([
            'repository' => 'acme/demo',
            'branch' => 'main',
        ]);

        $commands = $this->extractCommandStrings($provision);

        $this->assertSame(
            [
                "mkdir -p '/var/www/example.com/public'",
                "REPO_DIR='/var/www/example.com/public'; if [ -d \"\$REPO_DIR/.git\" ]; then cd \"\$REPO_DIR\" && git fetch --all --prune; else git clone 'git@github.com:acme/demo.git' \"\$REPO_DIR\"; fi",
                "REPO_DIR='/var/www/example.com/public'; cd \"\$REPO_DIR\" && git checkout 'main'",
                "REPO_DIR='/var/www/example.com/public'; cd \"\$REPO_DIR\" && git reset --hard origin/main && git pull origin 'main'",
            ],
            $commands,
        );
    }

    public function test_it_accepts_preformed_ssh_repository_urls(): void
    {
        $server = $this->makeServer();
        $site = $this->makeSite();

        $provision = new GitProvision($server);
        $provision->forSite($site)->setConfiguration([
            'repository' => 'git@github.com:acme/legacy.git',
            'branch' => 'develop',
        ]);

        $commands = $this->extractCommandStrings($provision);

        $this->assertStringContainsString("git clone 'git@github.com:acme/legacy.git'", $commands[1]);
        $this->assertSame(
            "REPO_DIR='/var/www/example.com/public'; cd \"\$REPO_DIR\" && git checkout 'develop'",
            $commands[2],
        );
    }

    public function test_it_derives_document_root_from_site_domain_when_missing(): void
    {
        $server = $this->makeServer();
        $site = $this->makeSite(documentRoot: null, domain: 'example.net');

        $provision = new GitProvision($server);
        $provision->forSite($site)->setConfiguration([
            'repository' => 'acme/demo',
        ]);

        $commands = $this->extractCommandStrings($provision);

        $this->assertSame(
            "mkdir -p '/var/www/example.net/public'",
            $commands[0],
        );
    }

    public function test_provision_requires_associated_site(): void
    {
        $server = $this->makeServer();
        $provision = new GitProvision($server);

        $this->expectException(LogicException::class);

        $provision->provision();
    }

    public function test_provision_requires_repository_configuration(): void
    {
        $server = $this->makeServer();
        $site = $this->makeSite();

        $provision = new GitProvision($server);
        $provision->forSite($site);

        $this->expectException(LogicException::class);

        $provision->provision();
    }

    public function test_invalid_branch_names_throw_exceptions(): void
    {
        $server = $this->makeServer();
        $site = $this->makeSite();

        $provision = new GitProvision($server);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Branch may only contain letters');

        $provision->forSite($site)->setConfiguration([
            'repository' => 'acme/demo',
            'branch' => 'feature~bad',
        ]);
    }

    public function test_invalid_document_root_rejections(): void
    {
        $server = $this->makeServer();
        $site = $this->makeSite();

        $provision = new GitProvision($server);

        $this->expectException(InvalidArgumentException::class);

        $provision->forSite($site)->setConfiguration([
            'repository' => 'acme/demo',
            'document_root' => '   ',
        ]);
    }

    public function test_repository_configuration_persistence_skips_when_site_unsaved(): void
    {
        $server = $this->makeServer();
        $site = $this->makeSite();

        $provision = new GitProvision($server);
        $provision->forSite($site)->setConfiguration([
            'repository' => 'acme/demo',
        ]);

        $commands = $this->extractRawCommands($provision);
        $persistClosure = end($commands);

        $this->assertIsCallable($persistClosure);

        $persistClosure();

        $this->assertNull($site->configuration);
    }

    public function test_repository_configuration_persists_when_site_exists(): void
    {
        $server = $this->makeServer();
        $site = new class(['domain' => 'example.com', 'document_root' => '/var/www/example.com/public']) extends ServerSite
        {
            public array $updates = [];

            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                $this->exists = true;
            }

            public function update(array $attributes = [], array $options = []): bool
            {
                $this->updates[] = $attributes;
                $this->setRawAttributes(array_merge($this->getAttributes(), $attributes));

                return true;
            }
        };

        $provision = new GitProvision($server);
        $provision->forSite($site)->setConfiguration([
            'repository' => 'acme/demo',
            'branch' => 'main',
            'provider' => 'github',
        ]);

        $commands = $this->extractRawCommands($provision);
        $persistClosure = end($commands);
        $this->assertIsCallable($persistClosure);

        $persistClosure();

        $this->assertNotEmpty($site->updates);
        $this->assertArrayHasKey('configuration', $site->updates[0]);
        $config = $site->updates[0]['configuration'];

        $this->assertArrayHasKey('git_repository', $config);
        $this->assertSame('github', $config['git_repository']['provider']);
        $this->assertSame('acme/demo', $config['git_repository']['repository']);
        $this->assertSame('main', $config['git_repository']['branch']);
        $this->assertNotEmpty($config['git_repository']['deploy_key']);
    }

    private function makeServer(): Server
    {
        return new Server([
            'public_ip' => '203.0.113.10',
            'ssh_port' => 2222,
            'ssh_root_user' => 'root',
            'ssh_app_user' => 'deployer',
        ]);
    }

    private function makeSite(?string $documentRoot = '/var/www/example.com/public', string $domain = 'example.com'): ServerSite
    {
        return new ServerSite([
            'domain' => $domain,
            'document_root' => $documentRoot,
        ]);
    }

    /**
     * Extract only the string commands from the provisioner, omitting milestone callbacks.
     *
     * @return array<int, string>
     */
    private function extractCommandStrings(GitProvision $provision): array
    {
        $commands = array_filter($this->extractRawCommands($provision), fn ($command) => is_string($command));

        return array_values($commands);
    }

    /**
     * Access the raw command payload emitted by the provisioner.
     *
     * @return array<int, \Closure|string>
     */
    private function extractRawCommands(GitProvision $provision): array
    {
        $method = new ReflectionMethod(GitProvision::class, 'commands');
        $method->setAccessible(true);

        /** @var array<int, \Closure|string> $commands */
        $commands = $method->invoke($provision);

        return $commands;
    }
}
