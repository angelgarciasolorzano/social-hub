<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function appearance(): Response
    {
        return Inertia::render('setting/modules/preference/Appearance');
    }
}
