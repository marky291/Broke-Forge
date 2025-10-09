<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Enums\PhpVersion;
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
        // Get installed PHP versions with eager loaded modules
        $installedPhp = $server->phps()->with('modules')->get();

        return Inertia::render('servers/php', [
            'server' => $server->only(['id', 'vanity_name', 'provider', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'monitoring_status', 'created_at', 'updated_at']),
            'availablePhpVersions' => PhpConfigurationService::getAvailableVersions(),
            'phpExtensions' => PhpConfigurationService::getAvailableExtensions(),
            'installedPhpVersions' => $installedPhp,
            'defaultSettings' => PhpConfigurationService::getDefaultSettings(),
            'latestMetrics' => $this->getLatestMetrics($server),
        ]);
    }

    public function store(Request $request, Server $server): RedirectResponse
    {
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
        } else {
            // Create new PHP version
            $php = ServerPhp::create([
                'server_id' => $server->id,
                'version' => $validated['version'],
                'status' => \App\Enums\PhpStatus::Installing,
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

        // TODO: Dispatch job to actually install PHP on the server

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP configuration saved successfully');
    }

    public function install(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', Rule::in(array_column(PhpVersion::cases(), 'value'))],
        ]);

        // Map version string to PhpVersion enum
        $phpVersion = PhpVersion::from($validated['version']);

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

        // Create PHP record with installing status
        ServerPhp::create([
            'server_id' => $server->id,
            'version' => $validated['version'],
            'status' => \App\Enums\PhpStatus::Installing,
            'is_cli_default' => $isFirstPhp, // First PHP version becomes CLI default
            'is_site_default' => $isFirstPhp, // First PHP version becomes Site default
        ]);

        // Dispatch installation job
        PhpInstallerJob::dispatch($server, $phpVersion);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP '.$validated['version'].' installation started');
    }

    public function setCliDefault(Server $server, ServerPhp $php): RedirectResponse
    {
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

        // Map version string to PhpVersion enum
        $phpVersion = PhpVersion::from($php->version);

        // Update PHP record to removing status
        $php->update(['status' => \App\Enums\PhpStatus::Removing]);

        // Dispatch removal job
        \App\Packages\Services\PHP\PhpRemoverJob::dispatch($server, $phpVersion, $php->id);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP '.$php->version.' removal started');
    }
}
