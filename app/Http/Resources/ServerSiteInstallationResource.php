<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Packages\Services\Sites\Framework\GenericPhp\GenericPhpInstallerJob;
use App\Packages\Services\Sites\Framework\Laravel\LaravelInstallerJob;
use App\Packages\Services\Sites\Framework\StaticHtml\StaticHtmlInstallerJob;
use App\Packages\Services\Sites\Framework\WordPress\WordPressInstallerJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerSiteInstallationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $installationState = $this->installation_state ?? collect();

        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'status' => $this->status,
            'framework' => $this->siteFramework->slug,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            'steps' => $this->getFrameworkSteps($installationState),
        ];
    }

    /**
     * Get framework-specific steps with their status.
     */
    protected function getFrameworkSteps($installationState): array
    {
        $stepDefinitions = $this->getStepDefinitionsForFramework();

        return collect($stepDefinitions)->map(function ($stepDef, $index) use ($installationState) {
            $stepNumber = $index + 1;

            return [
                'step' => $stepNumber,
                'name' => $stepDef['name'],
                'description' => $stepDef['description'],
                'status' => $this->getStepStatus($installationState, $stepNumber),
            ];
        })->values()->toArray();
    }

    /**
     * Get step definitions based on framework.
     */
    protected function getStepDefinitionsForFramework(): array
    {
        // Create temporary installer instance to get steps
        // Framework IDs: 1=Laravel, 2=WordPress, 3=GenericPhp, 4=StaticHtml
        $installer = match ($this->available_framework_id) {
            1 => new LaravelInstallerJob($this->server, $this->id),
            2 => new WordPressInstallerJob($this->server, $this->id),
            3 => new GenericPhpInstallerJob($this->server, $this->id),
            4 => new StaticHtmlInstallerJob($this->server, $this->id),
            default => null,
        };

        if (! $installer) {
            return [];
        }

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('getFrameworkSteps');
        $method->setAccessible(true);

        return $method->invoke($installer, $this->resource);
    }

    /**
     * Get the status for a specific installation step.
     */
    protected function getStepStatus($installationState, int $stepNumber): array
    {
        $status = $installationState->get($stepNumber, TaskStatus::Pending->value);

        return [
            'isCompleted' => $status === TaskStatus::Success->value,
            'isPending' => $status === TaskStatus::Pending->value,
            'isFailed' => $status === TaskStatus::Failed->value,
            'isInstalling' => $status === TaskStatus::Installing->value,
        ];
    }
}
