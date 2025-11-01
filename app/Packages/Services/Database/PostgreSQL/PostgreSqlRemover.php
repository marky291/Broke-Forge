<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\TaskStatus;
use App\Packages\Core\Base\PackageRemover;

class PostgreSqlRemover extends PackageRemover implements \App\Packages\Core\Base\ServerPackage
{
    /**
     * Mark PostgreSQL removal as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->server->databases()->latest()->first()?->update([
            'status' => TaskStatus::Failed,
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

            'mkdir -p /var/backups/postgresql-removal-$(date +%Y%m%d-%H%M%S) >/dev/null 2>&1 || true',
            'sudo -u postgres pg_dumpall > /var/backups/postgresql-removal-$(date +%Y%m%d-%H%M%S)/all-databases.sql 2>/dev/null || true',

            "DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge postgresql postgresql-{$majorVersion} postgresql-client-{$majorVersion} postgresql-contrib-{$majorVersion}",
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',

            'rm -rf /var/lib/postgresql',
            'rm -rf /var/log/postgresql',
            'rm -rf /etc/postgresql',

            'rm -f /etc/apt/sources.list.d/pgdg.list',
            'rm -f /usr/share/keyrings/postgresql-keyring.gpg',

            'userdel postgres >/dev/null 2>&1 || true',
            'groupdel postgres >/dev/null 2>&1 || true',

            'ufw delete allow 5432/tcp >/dev/null 2>&1 || true',

            'apt-get clean',
        ];
    }
}
