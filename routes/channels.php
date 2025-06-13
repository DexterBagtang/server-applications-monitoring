<?php

use App\Models\Server;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// routes/channels.php
Broadcast::channel('server.{serverId}.terminal', function ($user, $serverId) {
//    return $user->can('access-terminal', Server::findOrFail($serverId));
    return true;
});

Broadcast::channel('testing',function (){
    return true;
});
