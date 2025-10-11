<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $connection
 */
class ServerProvisioningResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $provision = $this->provision ?? [];

        return [
            'id' => $this->id,
            'vanity_name' => $this->vanity_name,
            'provider' => $this->provider,
            'public_ip' => $this->public_ip,
            'private_ip' => $this->private_ip,
            'ssh_port' => $this->ssh_port,
            'connection' => $this->connection,
            'monitoring_status' => $this->monitoring_status,
            'provision_status' => $this->provision_status->value,
            'os_name' => $this->os_name,
            'os_version' => $this->os_version,
            'os_codename' => $this->os_codename,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            'steps' => [
                [
                    'step' => 1,
                    'name' => 'Waiting on your server to become ready',
                    'description' => 'We are waiting to hear from your server to confirm the provisioning process has started. Hang tight, this could take a few minutes.',
                    'status' => $this->getStepStatus($provision, 1),
                ],
                [
                    'step' => 2,
                    'name' => 'Installing base dependencies',
                    'description' => 'Preparing your server',
                    'status' => $this->getStepStatus($provision, 2),
                ],
                [
                    'step' => 3,
                    'name' => 'Securing your server',
                    'description' => 'Installing base dependencies',
                    'status' => $this->getStepStatus($provision, 3),
                ],
                [
                    'name' => 'Installing Firewall',
                    'description' => 'Installing Firewall',
                    'status' => $this->getStepStatus($provision, 4),
                ],
                [
                    'name' => 'Installing PHP',
                    'description' => 'Installing PHP',
                    'status' => $this->getStepStatus($provision, 5),
                ],
                [
                    'name' => 'Installing Nginx',
                    'description' => 'Installing Nginx',
                    'status' => $this->getStepStatus($provision, 6),
                ],
                [
                    'name' => 'Making final touches',
                    'description' => 'Making final touches',
                    'status' => $this->getStepStatus($provision, 7),
                ],
            ],
        ];
    }

    /**
     * Get the status for a specific provision step
     */
    protected function getStepStatus(array $provision, int $stepNumber): array
    {
        $stepData = collect($provision)->firstWhere('step', $stepNumber);
        $status = $stepData['status'] ?? 'pending';

        return [
            'isCompleted' => $status === 'completed',
            'isPending' => $status === 'pending',
            'isFailed' => $status === 'failed',
            'isInstalling' => in_array($status, ['installing', 'connecting']),
        ];
    }
}
