<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\DatabaseStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

class PostgreSqlRemover extends PackageRemover implements \App\Packages\Base\ServerPackage
{
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

    public function milestones(): Milestones
    {
        return new PostgreSqlRemoverMilestones;
    }

    /**
     * Mark PostgreSQL removal as failed in database
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
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        $database = $this->server->databases()->latest()->first();
        $version = $database?->version ?? '16';
        $majorVersion = preg_replace('/[^0-9]/', '', $version) ?: '16';

        return [
            'systemctl stop postgresql >/dev/null 2>&1 || true',
            'systemctl disable postgresql >/dev/null 2>&1 || true',
            $this->track(PostgreSqlRemoverMilestones::STOP_SERVICE),

            'mkdir -p /var/backups/postgresql-removal-$(date +%Y%m%d-%H%M%S) >/dev/null 2>&1 || true',
            'sudo -u postgres pg_dumpall > /var/backups/postgresql-removal-$(date +%Y%m%d-%H%M%S)/all-databases.sql 2>/dev/null || true',
            $this->track(PostgreSqlRemoverMilestones::BACKUP_DATABASES),

            "DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge postgresql postgresql-{$majorVersion} postgresql-client-{$majorVersion} postgresql-contrib-{$majorVersion}",
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',
            $this->track(PostgreSqlRemoverMilestones::REMOVE_PACKAGES),

            'rm -rf /var/lib/postgresql',
            'rm -rf /var/log/postgresql',
            'rm -rf /etc/postgresql',
            $this->track(PostgreSqlRemoverMilestones::REMOVE_DATA_DIRECTORIES),

            'rm -f /etc/apt/sources.list.d/pgdg.list',
            'rm -f /usr/share/keyrings/postgresql-keyring.gpg',
            $this->track(PostgreSqlRemoverMilestones::REMOVE_REPOSITORY),

            'userdel postgres >/dev/null 2>&1 || true',
            'groupdel postgres >/dev/null 2>&1 || true',
            $this->track(PostgreSqlRemoverMilestones::REMOVE_USER_GROUP),

            'ufw delete allow 5432/tcp >/dev/null 2>&1 || true',
            $this->track(PostgreSqlRemoverMilestones::UPDATE_FIREWALL),

            fn () => $this->server->databases()->delete(),

            'apt-get clean',
            $this->track(PostgreSqlRemoverMilestones::UNINSTALLATION_COMPLETE),
        ];
    }
}
