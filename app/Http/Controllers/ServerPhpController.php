<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerPhpController extends Controller
{
    public function index(Server $server): Response
    {
        $availablePhpVersions = [
            '7.4' => 'PHP 7.4',
            '8.0' => 'PHP 8.0',
            '8.1' => 'PHP 8.1',
            '8.2' => 'PHP 8.2',
            '8.3' => 'PHP 8.3',
            '8.4' => 'PHP 8.4',
        ];

        $phpExtensions = [
            'bcmath' => 'BCMath - Arbitrary precision mathematics',
            'curl' => 'cURL - Client URL Library',
            'gd' => 'GD - Image processing',
            'intl' => 'Intl - Internationalization',
            'mbstring' => 'Multibyte String - String handling',
            'mysql' => 'MySQL - Database connectivity',
            'opcache' => 'OPcache - Bytecode cache',
            'pdo' => 'PDO - PHP Data Objects',
            'redis' => 'Redis - In-memory data structure store',
            'xml' => 'XML - XML parsing',
            'zip' => 'Zip - Archive handling',
        ];

        // Get installed PHP service
        $installedPhp = $server->services()
            ->where('service_name', 'php')
            ->first();

        return Inertia::render('servers/php', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
            'availablePhpVersions' => $availablePhpVersions,
            'phpExtensions' => $phpExtensions,
            'installedPhp' => $installedPhp,
        ]);
    }

    public function store(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'version' => 'required|in:7.4,8.0,8.1,8.2,8.3,8.4',
            'extensions' => 'array',
            'extensions.*' => 'string',
            'memory_limit' => 'nullable|string',
            'max_execution_time' => 'nullable|integer|min:1',
            'upload_max_filesize' => 'nullable|string',
        ]);

        // Check if PHP service already exists
        $existingService = $server->services()
            ->where('service_name', 'php')
            ->first();

        $configuration = [
            'version' => $validated['version'],
            'extensions' => $validated['extensions'] ?? [],
            'memory_limit' => $validated['memory_limit'] ?? '256M',
            'max_execution_time' => $validated['max_execution_time'] ?? 30,
            'upload_max_filesize' => $validated['upload_max_filesize'] ?? '2M',
        ];

        if ($existingService) {
            // Update existing service
            $existingService->update([
                'configuration' => $configuration,
                'status' => 'pending',
            ]);
        } else {
            // Create new service
            ServerPackage::create([
                'server_id' => $server->id,
                'service_name' => 'php',
                'configuration' => $configuration,
                'status' => 'pending',
            ]);
        }

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP configuration saved successfully');
    }
}
