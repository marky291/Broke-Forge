<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerNode;
use App\Packages\Services\Node\ComposerUpdaterJob;
use App\Packages\Services\Node\NodeInstallerJob;
use App\Packages\Services\Node\NodeRemoverJob;
use App\Packages\Services\Node\Services\NodeConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServerNodeController extends Controller
{
    public function index(Server $server): Response
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        return Inertia::render('servers/node', [
            'server' => new ServerResource($server),
        ]);
    }

    public function install(Request $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        $availableVersions = NodeConfigurationService::getAvailableVersions();

        $validated = $request->validate([
            'version' => ['required', 'string', Rule::in(array_keys($availableVersions))],
        ]);

        // Check if this Node version is already installed
        $existingNode = $server->nodes()
            ->where('version', $validated['version'])
            ->first();

        if ($existingNode) {
            return redirect()
                ->route('servers.node', $server)
                ->with('error', 'Node.js '.$validated['version'].' is already installed on this server');
        }

        // Check if this is the first Node version
        $isFirstNode = $server->nodes()->count() === 0;

        // ✅ CREATE RECORD FIRST with 'pending' status
        $node = ServerNode::create([
            'server_id' => $server->id,
            'version' => $validated['version'],
            'status' => TaskStatus::Pending,
            'is_default' => $isFirstNode, // First Node version becomes default
        ]);

        // ✅ THEN dispatch job with record
        NodeInstallerJob::dispatch($server, $node);

        return redirect()
            ->route('servers.node', $server)
            ->with('success', 'Node.js '.$validated['version'].' installation started');
    }

    public function setDefault(Server $server, ServerNode $node): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Verify the Node version belongs to this server
        if ($node->server_id !== $server->id) {
            abort(404);
        }

        // Unset current default
        $server->nodes()->update(['is_default' => false]);

        // Set new default
        $node->update(['is_default' => true]);

        return redirect()
            ->route('servers.node', $server)
            ->with('success', 'Node.js '.$node->version.' set as default');
    }

    public function destroy(Server $server, ServerNode $node): RedirectResponse
    {
        // Authorize user can delete this server
        $this->authorize('delete', $server);

        // Prevent removal if Node is default
        if ($node->is_default) {
            return redirect()
                ->route('servers.node', $server)
                ->with('error', 'Cannot remove Node.js '.$node->version.' as it is the default version');
        }

        // Verify the Node version belongs to this server
        if ($node->server_id !== $server->id) {
            abort(404);
        }

        // Update Node record to pending status
        $node->update(['status' => TaskStatus::Pending]);

        // Dispatch removal job with Node record
        NodeRemoverJob::dispatch($server, $node);

        return redirect()
            ->route('servers.node', $server)
            ->with('success', 'Node.js '.$node->version.' removal started');
    }

    /**
     * Retry a failed Node installation
     */
    public function retry(Server $server, ServerNode $node): RedirectResponse
    {
        $this->authorize('update', $server);

        // Verify the Node version belongs to this server
        if ($node->server_id !== $server->id) {
            abort(404);
        }

        // Only allow retry for failed Node installations
        if ($node->status !== TaskStatus::Failed) {
            return back()->with('error', 'Only failed Node installations can be retried');
        }

        // Audit log
        \Illuminate\Support\Facades\Log::info('Node installation retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'node_id' => $node->id,
            'node_version' => $node->version,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'pending' and clear error log
        // Model events will broadcast automatically via Reverb
        $node->update([
            'status' => TaskStatus::Pending,
            'error_log' => null,
        ]);

        // Re-dispatch installer job
        NodeInstallerJob::dispatch($server, $node);

        // No redirect needed - frontend will update via Reverb
        return back();
    }

    /**
     * Update Composer to the latest version
     */
    public function updateComposer(Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        // Check if Composer is installed
        if (! $server->composer_version) {
            return redirect()
                ->route('servers.node', $server)
                ->with('error', 'Composer is not installed on this server');
        }

        // Update Composer status to installing
        $server->update([
            'composer_status' => TaskStatus::Installing,
            'composer_error_log' => null,
        ]);

        // Dispatch Composer update job
        ComposerUpdaterJob::dispatch($server);

        return redirect()
            ->route('servers.node', $server)
            ->with('success', 'Composer update started');
    }

    /**
     * Retry a failed Composer update
     */
    public function retryComposer(Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        // Only allow retry for failed Composer updates
        if ($server->composer_status !== TaskStatus::Failed) {
            return back()->with('error', 'Only failed Composer updates can be retried');
        }

        // Audit log
        \Illuminate\Support\Facades\Log::info('Composer update retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'installing' and clear error log
        $server->update([
            'composer_status' => TaskStatus::Installing,
            'composer_error_log' => null,
        ]);

        // Re-dispatch updater job
        ComposerUpdaterJob::dispatch($server);

        return back();
    }
}
