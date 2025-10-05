<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\DatabaseStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

class PostgreSqlUpdater extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function milestones(): Milestones
    {
        return new PostgreSqlUpdaterMilestones;
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

    public function execute(string $targetVersion): void
    {
        $database = $this->server->databases()->latest()->first();
        $currentVersion = $database?->version ?? '16';
        $rootPassword = $database?->root_password ?? bin2hex(random_bytes(16));

        $this->install($this->commands($currentVersion, $targetVersion, $rootPassword));
    }

    protected function commands(string $currentVersion, string $targetVersion, string $rootPassword): array
    {
        $currentMajorVersion = preg_replace('/[^0-9]/', '', $currentVersion) ?: '16';
        $targetMajorVersion = preg_replace('/[^0-9]/', '', $targetVersion) ?: '16';
        $backupDir = '/var/backups/postgresql';
        $postgresConfigPath = "/etc/postgresql/{$targetMajorVersion}/main";
        $postgresConf = "{$postgresConfigPath}/postgresql.conf";
        $pgHbaConf = "{$postgresConfigPath}/pg_hba.conf";

        return [
            "mkdir -p {$backupDir}",
            "sudo -u postgres pg_dumpall > {$backupDir}/backup_before_update_$(date +%Y%m%d_%H%M%S).sql 2>/dev/null || echo 'Backup skipped'",
            $this->track(PostgreSqlUpdaterMilestones::BACKUP_DATABASE),

            'systemctl stop postgresql',
            $this->track(PostgreSqlUpdaterMilestones::STOP_SERVICE),

            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(PostgreSqlUpdaterMilestones::UPDATE_PACKAGES),

            "DEBIAN_FRONTEND=noninteractive apt-get install -y postgresql-{$targetMajorVersion} postgresql-client-{$targetMajorVersion}",
            $this->track(PostgreSqlUpdaterMilestones::UPGRADE_POSTGRESQL),

            "if [ -d /var/lib/postgresql/{$currentMajorVersion}/main ] && [ -d /var/lib/postgresql/{$targetMajorVersion}/main ]; then sudo -u postgres /usr/lib/postgresql/{$targetMajorVersion}/bin/pg_upgrade --old-datadir=/var/lib/postgresql/{$currentMajorVersion}/main --new-datadir=/var/lib/postgresql/{$targetMajorVersion}/main --old-bindir=/usr/lib/postgresql/{$currentMajorVersion}/bin --new-bindir=/usr/lib/postgresql/{$targetMajorVersion}/bin --check || true; fi",
            "if [ -d /var/lib/postgresql/{$currentMajorVersion}/main ] && [ -d /var/lib/postgresql/{$targetMajorVersion}/main ]; then sudo -u postgres /usr/lib/postgresql/{$targetMajorVersion}/bin/pg_upgrade --old-datadir=/var/lib/postgresql/{$currentMajorVersion}/main --new-datadir=/var/lib/postgresql/{$targetMajorVersion}/main --old-bindir=/usr/lib/postgresql/{$currentMajorVersion}/bin --new-bindir=/usr/lib/postgresql/{$targetMajorVersion}/bin || echo 'Migration skipped or failed'; fi",
            $this->track(PostgreSqlUpdaterMilestones::MIGRATE_DATA),

            // Configure remote access for new version
            "if [ -f {$postgresConf} ]; then sed -i \"s/#listen_addresses = 'localhost'/listen_addresses = '*'/\" {$postgresConf}; fi",
            "if [ -f {$pgHbaConf} ]; then grep -qxF \"host    all             all             0.0.0.0/0               md5\" {$pgHbaConf} || echo \"host    all             all             0.0.0.0/0               md5\" >> {$pgHbaConf}; fi",
            "if [ -f {$pgHbaConf} ]; then grep -qxF \"host    all             all             ::/0                    md5\" {$pgHbaConf} || echo \"host    all             all             ::/0                    md5\" >> {$pgHbaConf}; fi",

            'systemctl enable --now postgresql',
            $this->track(PostgreSqlUpdaterMilestones::START_SERVICE),

            'systemctl status postgresql --no-pager',
            "sudo -u postgres psql -c 'SELECT version();'",
            $this->track(PostgreSqlUpdaterMilestones::VERIFY_UPDATE),

            fn () => $this->server->databases()->latest()->first()?->update([
                'status' => DatabaseStatus::Active->value,
                'version' => $targetVersion,
            ]),

            $this->track(PostgreSqlUpdaterMilestones::UPDATE_COMPLETE),
        ];
    }
}
