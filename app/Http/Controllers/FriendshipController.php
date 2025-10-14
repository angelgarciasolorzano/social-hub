<?php

namespace App\Http\Controllers;

use App\Events\Test;
use App\Http\Requests\StoreFriendshipRequest;
use App\Http\Requests\UpdateFriendshipRequest;
//use App\Models\Friendship;
//use App\Models\Friendship;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use function Laravel\Prompts\search;

class FriendshipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFriendshipRequest $request, User $user)
    {

    }

    public  function sendRequest($receiver_id)
    {
        $receiver = User::query()->findOrFail($receiver_id);

        $exists = Friendship::query()->where('receiver_id', $receiver_id)->exists();

//        if ($exists) {
//            return back()->with('notification', [
//                'type' => 'error',
//                'message' => 'Ya enviaste una solicitud de amistad a este usuario'
//            ]);
//        };

        $friendship = Friendship::create([
            'requester_id' => Auth::id(),
            'receiver_id' => $receiver_id,
            'status' => "pending"
        ]);

        $receiver->notify(new FriendRequestNotification($friendship));

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Solicitud enviada correctamente'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Friendship $friendship)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Friendship $friendship)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFriendshipRequest $request, Friendship $friendship)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Friendship $friendship)
    {
        //
    }

    public function search(Request $request)
    {
        $search = $request->input('search');

        $users = User::query()
            ->where('id', '!=', Auth::id())
            ->where('name', 'Like', $search."%")
            ->limit(100)
            ->get();

        return back()->with('search_results', $users);
    }
}
