<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFriendshipRequest;
use App\Http\Requests\UpdateFriendshipRequest;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    public function store(StoreFriendshipRequest $request)
    {
        //
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
        logger($request->all());
        $search = $request->input('q');

        $users = User::query()
            ->where('id', '!=', Auth::id())
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%');
                });
            })
            ->limit(10)
            ->get();

        logger($users);

        return back()->with('search_results', $users);
    }
}
