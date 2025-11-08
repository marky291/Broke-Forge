<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use Illuminate\Http\RedirectResponse;

class ServerSiteGitController extends Controller
{
    /**
     * Cancel a stuck Git installation and reset to failed state.
     */
    public function cancel(Server $server, ServerSite $site): RedirectResponse
    {
        // Only allow cancellation if currently installing
        if ($site->git_status !== TaskStatus::Installing) {
            return back()->with('error', 'Installation is not in progress.');
        }

        // Reset to failed state so user can retry
        $site->update([
            'git_status' => TaskStatus::Failed,
        ]);

        return redirect()
            ->route('servers.sites.settings.git.setup', [$server, $site])
            ->with('success', 'Installation cancelled. You can retry now.');
    }
}
