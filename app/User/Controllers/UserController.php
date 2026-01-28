<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use App\User\Enums\UserImageType;
use App\User\Requests\UserImageUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class UserController extends Controller {
    public function updateImage(UserImageUpdateRequest $request, string $type): RedirectResponse
    {
        $typeImage = UserImageType::tryFrom($type);

        if (!$typeImage) {
            return Inertia::flash([
                'type' => 'error',
                'message' => UserImageType::invalidTypeMessage(),
            ])->back();
        }

        $user = Auth::user();

        try {
            $user->addMediaFromRequest($typeImage->value())->toMediaCollection($typeImage->value());
        } catch (FileDoesNotExist | FileIsTooBig $exception) {
            return Inertia::flash([
                'type' => 'error',
                'message' => $typeImage->errorMessage(),
            ])->back();
        }
        
        return Inertia::flash([
            'type' => 'success',
            'message' => $typeImage->successMessage(),
        ])->back();
    }
}
