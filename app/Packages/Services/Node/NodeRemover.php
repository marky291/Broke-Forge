<?php

namespace App\Packages\Services\Node;

use App\Enums\TaskStatus;
use App\Models\ServerNode;
use App\Packages\Core\Base\PackageRemover;
use App\Packages\Core\Base\ServerPackage;
use App\Packages\Enums\NodeVersion;

/**
 * Node.js Removal Class
 *
 * Handles removal of Node.js with progress tracking
 */
class NodeRemover extends PackageRemover implements ServerPackage
{
    /**
     * The Node version being removed
     */
    private NodeVersion $removingVersion;

    /**
     * The ServerNode model ID being removed
     */
    private int $nodeId;

    /**
     * Mark Node removal as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        ServerNode::where('id', $this->nodeId)
            ->update(['status' => TaskStatus::Failed, 'error_log' => $errorMessage]);
    }

    /**
     * Execute Node.js removal with the specified version
     */
    public function execute(NodeVersion $nodeVersion, int $nodeId): void
    {
        // Store the version and ID for failure handling
        $this->removingVersion = $nodeVersion;
        $this->nodeId = $nodeId;

        $this->remove($this->commands($nodeVersion));
    }

    protected function commands(NodeVersion $nodeVersion): array
    {
        return [
            // Stop any Node.js processes (optional, but safer)
            'pkill -f node || true',

            // Remove Node.js packages
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge nodejs npm',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',

            // Clean up NodeSource repository
            'rm -f /etc/apt/sources.list.d/nodesource.list',
            'rm -f /usr/share/keyrings/nodesource.gpg',

            // Clean up any remaining Node.js files
            'rm -rf /usr/lib/node_modules',
            'rm -rf /usr/local/lib/node_modules',
            'rm -f /usr/local/bin/node',
            'rm -f /usr/local/bin/npm',

            // Delete Node record from database
            fn () => ServerNode::where('id', $this->nodeId)->delete(),
        ];
    }
}
