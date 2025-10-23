<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Packages\Base\PackageInstaller;

class PostgreSqlInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function execute(string $version, string $rootPassword): void
    {
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

            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            'curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/postgresql-keyring.gpg',
            'echo "deb [signed-by=/usr/share/keyrings/postgresql-keyring.gpg] http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list',
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            "DEBIAN_FRONTEND=noninteractive apt-get install -y postgresql-{$majorVersion} postgresql-client-{$majorVersion}",

            'systemctl enable --now postgresql',

            "sudo -u postgres psql -c \"ALTER USER postgres WITH PASSWORD '{$rootPassword}';\"",

            "if [ -f {$postgresConf} ]; then sed -i \"s/#listen_addresses = 'localhost'/listen_addresses = '*'/\" {$postgresConf}; fi",
            "if [ -f {$pgHbaConf} ]; then grep -qxF \"host    all             all             0.0.0.0/0               md5\" {$pgHbaConf} || echo \"host    all             all             0.0.0.0/0               md5\" >> {$pgHbaConf}; fi",
            "if [ -f {$pgHbaConf} ]; then grep -qxF \"host    all             all             ::/0                    md5\" {$pgHbaConf} || echo \"host    all             all             ::/0                    md5\" >> {$pgHbaConf}; fi",

            'systemctl restart postgresql',

            'ufw allow 5432/tcp >/dev/null 2>&1 || true',

            'systemctl status postgresql --no-pager',
            "sudo -u postgres psql -c 'SELECT version();'",

        ];
    }
}
