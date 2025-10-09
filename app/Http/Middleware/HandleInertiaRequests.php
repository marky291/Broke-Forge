<?php

namespace App\Http\Middleware;

use App\Models\Server;
use App\Models\ServerSite;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Load servers and sites for search functionality
        $searchData = [];
        if ($request->user()) {
            $searchData['searchServers'] = Server::select('id', 'vanity_name', 'public_ip', 'provider')
                ->orderBy('vanity_name')
                ->get()
                ->map(fn (Server $server) => [
                    'id' => $server->id,
                    'name' => $server->vanity_name,
                    'public_ip' => $server->public_ip,
                    'provider' => $server->provider?->value,
                ]);

            $searchData['searchSites'] = ServerSite::with(['server:id,vanity_name'])
                ->select('id', 'server_id', 'domain')
                ->whereNotNull('domain')
                ->orderBy('domain')
                ->get()
                ->map(fn (ServerSite $site) => [
                    'id' => $site->id,
                    'server_id' => $site->server_id,
                    'domain' => $site->domain,
                    'server_name' => $site->server?->vanity_name,
                ]);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->toArray(),
                    'plan_name' => $request->user()->getCurrentPlanName(),
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ...$searchData,
        ];
    }
}
