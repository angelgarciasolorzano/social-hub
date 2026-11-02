<?php

declare(strict_types=1);

namespace App\User\Preferences\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Translation\FrontendTranslations;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller
{
    /**
     * The page itself is not translated yet; `setting/common` is registered for the settings sidebar it shares.
     */
    public function appearance(FrontendTranslations $frontendTranslations): Response
    {
        $frontendTranslations->use('setting/common');

        return Inertia::render('setting/modules/preference/Appearance');
    }
}
