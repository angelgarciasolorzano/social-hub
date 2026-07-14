<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrustedDeviceController extends Controller
{
    public function destroy(Request $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        return back();
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        return back();
    }
}
