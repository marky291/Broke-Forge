<?php

use App\Models\Server;
use App\Models\ServerSite;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('servers.{serverId}', function ($user, int $serverId) {
    return $user->id === Server::findOrNew($serverId)->user_id;
});

Broadcast::channel('sites.{siteId}', function ($user, int $siteId) {
    $site = ServerSite::with('server')->find($siteId);

    return $site && $user->id === $site->server->user_id;
});
