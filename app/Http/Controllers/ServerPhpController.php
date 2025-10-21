<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Services\PHP\PhpInstallerJob;
use App\Packages\Services\PHP\Services\PhpConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServerPhpController extends Controller
{
    use PreparesSiteData;

    public function index(Server $server): Response
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        return Inertia::render('servers/php', [
            'server' => new ServerResource($server),
        ]);
    }

    public function store(Request $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        $validated = $request->validate(PhpConfigurationService::getValidationRules());

        // Check if this PHP version already exists
        $existingPhp = $server->phps()
            ->where('version', $validated['version'])
            ->first();

        if ($existingPhp) {
            // Update existing PHP version
            $existingPhp->update([
                'status' => \App\Enums\PhpStatus::Installing,
                'is_cli_default' => $validated['is_cli_default'] ?? $existingPhp->is_cli_default,
            ]);

            // Update modules
            if (isset($validated['extensions'])) {
                // Remove modules not in the list
                $existingPhp->modules()->whereNotIn('name', $validated['extensions'])->delete();

                // Add or update modules
                foreach ($validated['extensions'] as $extension) {
                    $existingPhp->modules()->updateOrCreate(
                        ['name' => $extension],
                        ['is_enabled' => true]
                    );
                }
            }

            $php = $existingPhp;
        } else {
            // Create new PHP version
            $php = ServerPhp::create([
                'server_id' => $server->id,
                'version' => $validated['version'],
                'status' => \App\Enums\PhpStatus::Pending,
                'is_cli_default' => $validated['is_cli_default'] ?? false,
            ]);

            // Add modules
            if (isset($validated['extensions'])) {
                foreach ($validated['extensions'] as $extension) {
                    $php->modules()->create([
                        'name' => $extension,
                        'is_enabled' => true,
                    ]);
                }
            }
        }

        // Dispatch job to actually install PHP on the server
        PhpInstallerJob::dispatch($server, $php->id);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP installation started');
    }

    public function install(Request $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        $availableVersions = PhpConfigurationService::getAvailableVersions();

        $validated = $request->validate([
            'version' => ['required', 'string', Rule::in(array_keys($availableVersions))],
        ]);

        // Check if this PHP version is already installed
        $existingPhp = $server->phps()
            ->where('version', $validated['version'])
            ->first();

        if ($existingPhp) {
            return redirect()
                ->route('servers.php', $server)
                ->with('error', 'PHP '.$validated['version'].' is already installed on this server');
        }

        // Check if this is the first PHP version
        $isFirstPhp = $server->phps()->count() === 0;

        // ✅ CREATE RECORD FIRST with 'pending' status
        $php = ServerPhp::create([
            'server_id' => $server->id,
            'version' => $validated['version'],
            'status' => \App\Enums\PhpStatus::Pending,
            'is_cli_default' => $isFirstPhp, // First PHP version becomes CLI default
            'is_site_default' => $isFirstPhp, // First PHP version becomes Site default
        ]);

        // ✅ THEN dispatch job with record ID
        PhpInstallerJob::dispatch($server, $php->id);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP '.$validated['version'].' installation started');
    }

    public function setCliDefault(Server $server, ServerPhp $php): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Verify the PHP version belongs to this server
        if ($php->server_id !== $server->id) {
            abort(404);
        }

        // Unset current CLI default
        $server->phps()->update(['is_cli_default' => false]);

        // Set new CLI default
        $php->update(['is_cli_default' => true]);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP '.$php->version.' set as CLI default');
    }

    public function setSiteDefault(Server $server, ServerPhp $php): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Verify the PHP version belongs to this server
        if ($php->server_id !== $server->id) {
            abort(404);
        }

        // Unset current Site default
        $server->phps()->update(['is_site_default' => false]);

        // Set new Site default
        $php->update(['is_site_default' => true]);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP '.$php->version.' set as Site default');
    }

    public function destroy(Server $server, ServerPhp $php): RedirectResponse
    {
        // Authorize user can delete this server
        $this->authorize('delete', $server);

        // Prevent removal if PHP is CLI default
        if ($php->is_cli_default) {
            return redirect()
                ->route('servers.php', $server)
                ->with('error', 'Cannot remove PHP '.$php->version.' as it is the CLI default version');
        }

        // Prevent removal if PHP is Site default
        if ($php->is_site_default) {
            return redirect()
                ->route('servers.php', $server)
                ->with('error', 'Cannot remove PHP '.$php->version.' as it is the Site default version');
        }

        // Verify the PHP version belongs to this server
        if ($php->server_id !== $server->id) {
            abort(404);
        }

        // Update PHP record to removing status
        $php->update(['status' => \App\Enums\PhpStatus::Removing]);

        // Dispatch removal job with PHP record ID
        \App\Packages\Services\PHP\PhpRemoverJob::dispatch($server, $php->id);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP '.$php->version.' removal started');
    }

    /**
     * Retry a failed PHP installation
     */
    public function retry(Server $server, ServerPhp $php): RedirectResponse
    {
        $this->authorize('update', $server);

        // Verify the PHP version belongs to this server
        if ($php->server_id !== $server->id) {
            abort(404);
        }

        // Only allow retry for failed PHP installations
        if ($php->status !== \App\Enums\PhpStatus::Failed) {
            return back()->with('error', 'Only failed PHP installations can be retried');
        }

        // Audit log
        \Illuminate\Support\Facades\Log::info('PHP installation retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'php_id' => $php->id,
            'php_version' => $php->version,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'pending' and clear error log
        // Model events will broadcast automatically via Reverb
        $php->update([
            'status' => \App\Enums\PhpStatus::Pending,
            'error_log' => null,
        ]);

        // Re-dispatch installer job
        PhpInstallerJob::dispatch($server, $php->id);

        // No redirect needed - frontend will update via Reverb
        return back();
    }
}
