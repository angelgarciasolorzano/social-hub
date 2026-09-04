<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Requests;

use App\Auth\Modules\TrustedDevice\Concerns\HasPasswordConfirmation;
use Illuminate\Foundation\Http\FormRequest;

class TrustedDeviceDestroyForceRequest extends FormRequest
{
    use HasPasswordConfirmation;
}
