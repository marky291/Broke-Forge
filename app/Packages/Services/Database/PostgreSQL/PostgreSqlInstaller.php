<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\DatabaseStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

class PostgreSqlInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function milestones(): Milestones
    {
        return new PostgreSqlInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    public function packageName(): PackageName
    {
        return PackageName::PostgreSql;
    }

    public function packageType(): PackageType
    {
        return PackageType::Database;
    }

    /**
     * Mark PostgreSQL installation as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->server->databases()->latest()->first()?->update([
            'status' => DatabaseStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    public function execute(): void
    {
        $database = $this->server->databases()->latest()->first();
        $version = $database?->version ?? '16';
        $rootPassword = $database?->root_password ?? bin2hex(random_bytes(16));

        $this->install($this->commands($version, $rootPassword));
    }

    protected function commands(string $version, string $rootPassword): array
    {
        $majorVersion = preg_replace('/[^0-9]/', '', $version) ?: '16';
        $postgresConfigPath = "/etc/postgresql/{$majorVersion}/main";
        $postgresConf = "{$postgresConfigPath}/postgresql.conf";
        $pgHbaConf = "{$postgresConfigPath}/pg_hba.conf";

        return [
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(PostgreSqlInstallerMilestones::UPDATE_PACKAGES),

            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',
            $this->track(PostgreSqlInstallerMilestones::INSTALL_PREREQUISITES),

            'curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/postgresql-keyring.gpg',
            'echo "deb [signed-by=/usr/share/keyrings/postgresql-keyring.gpg] http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list',
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(PostgreSqlInstallerMilestones::ADD_REPOSITORY),

            "DEBIAN_FRONTEND=noninteractive apt-get install -y postgresql-{$majorVersion} postgresql-client-{$majorVersion}",
            $this->track(PostgreSqlInstallerMilestones::INSTALL_POSTGRESQL),

            'systemctl enable --now postgresql',
            $this->track(PostgreSqlInstallerMilestones::START_SERVICE),

            "sudo -u postgres psql -c \"ALTER USER postgres WITH PASSWORD '{$rootPassword}';\"",
            $this->track(PostgreSqlInstallerMilestones::CONFIGURE_ROOT_PASSWORD),

            "if [ -f {$postgresConf} ]; then sed -i \"s/#listen_addresses = 'localhost'/listen_addresses = '*'/\" {$postgresConf}; fi",
            "if [ -f {$pgHbaConf} ]; then grep -qxF \"host    all             all             0.0.0.0/0               md5\" {$pgHbaConf} || echo \"host    all             all             0.0.0.0/0               md5\" >> {$pgHbaConf}; fi",
            "if [ -f {$pgHbaConf} ]; then grep -qxF \"host    all             all             ::/0                    md5\" {$pgHbaConf} || echo \"host    all             all             ::/0                    md5\" >> {$pgHbaConf}; fi",
            $this->track(PostgreSqlInstallerMilestones::CONFIGURE_REMOTE_ACCESS),

            'systemctl restart postgresql',
            $this->track(PostgreSqlInstallerMilestones::RESTART_SERVICE),

            'ufw allow 5432/tcp >/dev/null 2>&1 || true',
            $this->track(PostgreSqlInstallerMilestones::CONFIGURE_FIREWALL),

            'systemctl status postgresql --no-pager',
            "sudo -u postgres psql -c 'SELECT version();'",
            $this->track(PostgreSqlInstallerMilestones::VERIFY_INSTALLATION),

            fn () => $this->server->databases()->latest()->first()?->update([
                'status' => DatabaseStatus::Active->value,
                'version' => $version,
                'port' => 5432,
                'root_password' => $rootPassword,
            ]),

            $this->track(PostgreSqlInstallerMilestones::INSTALLATION_COMPLETE),
        ];
    }
}
