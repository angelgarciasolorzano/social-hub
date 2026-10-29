<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocale
{
    /**
     * The language of a guest whose browser asks for no supported one. It is Spanish, the language the
     * application was written in, and it differs from `app.locale` on purpose: the admin panel needs that one in English.
     */
    public const PreferredLocale GUEST_FALLBACK_LOCALE = PreferredLocale::SPANISH;

    /**
     * Apply the signed-in user's preferred language, or the browser's one for a guest.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request)->value);

        return $next($request);
    }

    /**
     * The user's stored language when it is supported, otherwise the browser's, otherwise the guest fallback.
     */
    private function resolveLocale(Request $request): PreferredLocale
    {
        return $this->storedLocale($request->user())
            ?? $this->browserLocale($request)
            ?? self::GUEST_FALLBACK_LOCALE;
    }

    /**
     * The raw column is read so that an unsupported value written outside the model does not throw.
     */
    private function storedLocale(mixed $user): ?PreferredLocale
    {
        if (! $user instanceof User) {
            return null;
        }

        $storedLocale = $user->getRawOriginal('preferred_locale');

        return \is_string($storedLocale) ? PreferredLocale::tryFrom($storedLocale) : null;
    }

    /**
     * The first supported language of `Accept-Language`, in the browser's order of preference.
     * `getPreferredLanguage()` is not used: it returns the first offered language even when none matches.
     */
    private function browserLocale(Request $request): ?PreferredLocale
    {
        foreach ($request->getLanguages() as $language) {
            $supportedLocale = PreferredLocale::tryFrom(Str::lower(Str::before($language, '_')));

            if ($supportedLocale instanceof PreferredLocale) {
                return $supportedLocale;
            }
        }

        return null;
    }
}
