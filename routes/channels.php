<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('leads', function ($user) {
    return $user->canViewAllLeads();
});

Broadcast::channel('site-visits', function ($user) {
    return $user->canViewAllLeads() || $user->isSalesManager();
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('telecaller.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId && $user->role && $user->role->slug === 'sales_executive';
});

// Admin broadcast channel - all authenticated users can listen
Broadcast::channel('admin-broadcast', function ($user) {
    return $user !== null;
});