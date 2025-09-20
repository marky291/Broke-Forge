<?php

namespace App\Provision\Server\WebServer;

use App\Provision\Enums\ExecutableUser;
use App\Provision\Enums\ServiceType;
use App\Provision\Milestones;
use App\Provision\RemovableService;

class WebServiceDeprovision extends RemovableService
{

    protected function serviceType(): string
    {
        return ServiceType::WEBSERVER;
    }

    protected function milestones(): Milestones
    {
        return new WebServiceDeprovisionMilestones;
    }

    protected function executableUser(): ExecutableUser
    {
        return ExecutableUser::RootUser;
    }

    /**
     * Build a web server setup command list using a Server model.
     */
    public function deprovision(): void
    {
        $phpService = $this->server->services()->where('service_name', 'php')->latest('id')->first();
        $phpVersion = $phpService->configuration['version'];

        // Compose common PHP packages for the chosen version.
        $phpPackages = implode(' ', [
            "php{$phpVersion}-fpm",
            "php{$phpVersion}-cli",
            "php{$phpVersion}-common",
            "php{$phpVersion}-curl",
            "php{$phpVersion}-mbstring",
            "php{$phpVersion}-xml",
            "php{$phpVersion}-zip",
            "php{$phpVersion}-intl",
            "php{$phpVersion}-mysql",
            "php{$phpVersion}-gd",
        ]);

        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            //
        ];
    }
}
