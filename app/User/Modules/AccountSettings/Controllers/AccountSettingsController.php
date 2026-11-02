<?php

declare(strict_types=1);

namespace App\User\Modules\AccountSettings\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Translation\FrontendTranslations;
use App\Support\Translation\TranslatedText;
use App\User\Events\AccountUpdated;
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

    public function edit(Request $request, FrontendTranslations $frontendTranslations): Response
    {
        $user = $this->getAuthenticatedUser($request);

        $frontendTranslations->use('setting/common', 'setting/account-settings');

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

        if ($user->wasChanged()) {
            event(AccountUpdated::fromSavedChanges($user));
        }

        app()->setLocale($user->preferred_locale->value);

        return Inertia::flash([
            'type' => 'success',
            'message' => TranslatedText::of('setting/account-settings.messages.profile_updated'),
        ])->back();
    }
}
