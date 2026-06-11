<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use App\User\Requests\UserImageUpdateRequest;
use App\User\Requests\UserUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserController extends Controller
{
    private function getAuthenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function update(UserUpdateRequest $request): RedirectResponse
    {
        $user = $this->getAuthenticatedUser();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

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

        $user = $this->getAuthenticatedUser();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function updateImage(UserImageUpdateRequest $request, UserImageType $type): RedirectResponse
    {
        $user = $this->getAuthenticatedUser();

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
