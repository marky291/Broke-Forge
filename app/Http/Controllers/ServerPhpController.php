<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Services\PHP\Services\PhpConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerPhpController extends Controller
{
    public function index(Server $server): Response
    {
        // Get installed PHP versions with eager loaded modules
        $installedPhp = $server->phps()->with('modules')->get();

        return Inertia::render('servers/php', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
            'availablePhpVersions' => PhpConfigurationService::getAvailableVersions(),
            'phpExtensions' => PhpConfigurationService::getAvailableExtensions(),
            'installedPhpVersions' => $installedPhp,
            'defaultSettings' => PhpConfigurationService::getDefaultSettings(),
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
}
