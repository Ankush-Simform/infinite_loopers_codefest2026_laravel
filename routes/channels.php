<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('reports.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});
