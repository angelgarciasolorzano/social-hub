<?php

declare(strict_types=1);

namespace App\User\Modules\AccountSettings\Controllers;

use App\Http\Controllers\Controller;
use App\User\Models\User;
use App\User\Modules\AccountSettings\Data\AccountSettingsUpdateData;
use App\User\Modules\AccountSettings\Requests\AccountSettingsUpdateRequest;
use App\User\Modules\AccountSettings\Resources\AccountSettingsResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AccountSettingsController extends Controller
{
    private function getAuthenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function edit(Request $request): Response
    {
        $user = $this->getAuthenticatedUser($request);

        return Inertia::render('setting/modules/accountSettings/AccountSettings', [
            'accountSettings' => new AccountSettingsResource($user)->resolve($request),
        ]);
    }

    public function update(AccountSettingsUpdateRequest $accountSettingsUpdateRequest): RedirectResponse
    {
        $user = $this->getAuthenticatedUser($accountSettingsUpdateRequest);

        $accountSettingsUpdateData = AccountSettingsUpdateData::from(
            $accountSettingsUpdateRequest->validated(),
        );

        $user->fill($accountSettingsUpdateData->toUserAttributes())->save();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Datos actualizados correctamente.',
        ])->back();
    }
}
