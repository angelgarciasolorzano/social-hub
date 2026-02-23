<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use App\User\Enums\UserImageType;
use App\User\Requests\UserImageUpdateRequest;
use App\User\Requests\UserUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class UserController extends Controller
{
    public function update(UserUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Datos actualizados correctamente.',
        ])->back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function updateImage(UserImageUpdateRequest $request, UserImageType $type): RedirectResponse
    {
        $user = Auth::user();

        try {
            $user->addMediaFromRequest($type->value())->toMediaCollection($type->value());
        } catch (FileDoesNotExist|FileIsTooBig $exception) {
            return Inertia::flash([
                'type' => 'error',
                'message' => $type->errorMessage(),
            ])->back();
        }

        return Inertia::flash([
            'type' => 'success',
            'message' => $type->successMessage(),
        ])->back();
    }
}
