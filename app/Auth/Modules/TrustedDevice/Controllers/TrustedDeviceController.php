<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceRegistrationResult;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyAllRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyForceRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceReactivateRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceUpdateRequest;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrustedDeviceController extends Controller
{
    use MintsTrustedDeviceToken;

    public function __construct(private readonly TrustedDeviceService $trustedDeviceService) {}

    public function update(TrustedDeviceUpdateRequest $trustedDeviceUpdateRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        $user = $trustedDeviceUpdateRequest->user();

        abort_unless($user instanceof User && $trustedDevice->user_id === $user->getKey(), 403);

        $this->trustedDeviceService->rename(
            $user,
            $trustedDevice,
            $trustedDeviceUpdateRequest->string('name')->toString(),
            $trustedDeviceUpdateRequest,
        );

        return Inertia::flash(TrustedDeviceRegistrationResult::Renamed->payload())->back();
    }

    public function renew(Request $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $trustedDevice->user_id === $user->getKey(), 403);

        $this->trustedDeviceService->renew($user, $trustedDevice, $request);

        return Inertia::flash(TrustedDeviceRegistrationResult::Renewed->payload())->back();
    }

    public function store(TrustedDeviceStoreRequest $trustedDeviceStoreRequest): RedirectResponse
    {
        $user = $trustedDeviceStoreRequest->user();

        abort_unless($user instanceof User, 401);

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $token = $this->mintToken();

        $trustedDeviceRegistrationResult = $this->trustedDeviceService->register(
            $user,
            $deviceDetector,
            $trustedDeviceStoreRequest,
            $token['hash'],
            $trustedDeviceStoreRequest->string('name')->toString(),
        );

        if ($trustedDeviceRegistrationResult->isSuccessful()) {
            $this->queueTrustedDeviceCookie($token['token']);
        }

        return Inertia::flash($trustedDeviceRegistrationResult->payload())->back();
    }

    public function destroy(TrustedDeviceDestroyRequest $trustedDeviceDestroyRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        $user = $trustedDeviceDestroyRequest->user();

        abort_unless($user instanceof User && $trustedDevice->user_id === $user->getKey(), 403);

        $this->trustedDeviceService->revoke($user, $trustedDevice, $trustedDeviceDestroyRequest);

        return Inertia::flash(TrustedDeviceRegistrationResult::Revoked->payload())->back();
    }

    public function destroyAll(TrustedDeviceDestroyAllRequest $trustedDeviceDestroyAllRequest): RedirectResponse
    {
        $user = $trustedDeviceDestroyAllRequest->user();

        abort_unless($user instanceof User, 401);

        $this->trustedDeviceService->revokeAll($user, $trustedDeviceDestroyAllRequest);

        return Inertia::flash(TrustedDeviceRegistrationResult::RevokedAll->payload())->back();
    }

    public function reactivate(TrustedDeviceReactivateRequest $trustedDeviceReactivateRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        $user = $trustedDeviceReactivateRequest->user();

        abort_unless($user instanceof User, 401);
        abort_unless($trustedDevice->user_id === $user->getKey(), 403);
        abort_if($trustedDevice->deleted_at === null, 404);

        $newToken = $this->trustedDeviceService->reactivate($user, $trustedDevice, $trustedDeviceReactivateRequest);

        $this->queueTrustedDeviceCookie($newToken);

        return Inertia::flash(TrustedDeviceRegistrationResult::Reactivated->payload())->back();
    }

    public function forceDestroy(TrustedDeviceDestroyForceRequest $trustedDeviceDestroyForceRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        $user = $trustedDeviceDestroyForceRequest->user();

        abort_unless($user instanceof User && $trustedDevice->user_id === $user->getKey(), 403);
        abort_if($trustedDevice->deleted_at === null, 404);

        $this->trustedDeviceService->forceDelete($user, $trustedDevice, $trustedDeviceDestroyForceRequest);

        return Inertia::flash(TrustedDeviceRegistrationResult::ForceDeleted->payload())->back();
    }
}
