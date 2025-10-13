<?php

use App\Models\Server;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('servers.{serverId}.provision', function ($user, int $serverId) {
    return $user->id === Server::findOrNew($serverId)->user_id;
});
