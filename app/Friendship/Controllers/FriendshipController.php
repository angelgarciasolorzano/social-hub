<?php

namespace App\Friendship\Controllers;

use App\Friendship\Enums\FriendshipStatus;
use App\Friendship\Models\Friendship;
use App\Http\Controllers\Controller;
use App\Notifications\FriendRequestNotification;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FriendshipController extends Controller
{
    public  function sendRequest($receiver_id)
    {
        $exists = Friendship::query()->where('receiver_id', $receiver_id)->exists();

       if ($exists) {
           return Inertia::flash('notification', [
               'type' => 'error',
               'message' => 'Ya enviaste una solicitud de amistad a este usuario'
           ])->back();
       };

        Friendship::create([
            'requester_id' => Auth::id(),
            'receiver_id' => $receiver_id,
            'status' => FriendshipStatus::PENDING
        ]);

        return Inertia::flash('notification', [
            'type' => 'success',
            'message' => 'Solicitud enviada correctamente'
        ])->back();
    }

    public function search(Request $request)
    {
        $search = $request->input('search');

        $users = User::query()
            ->where('id', '!=', Auth::id())
            ->where('name', 'Like', $search."%")
            ->limit(100)
            ->get();

        return Inertia::flash('search_results', $users)->back();
    }
}
