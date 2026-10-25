<?php

declare(strict_types=1);

use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Suspend an account that already has an open session.
 */
function suspendSignedInAccount(User $user): void
{
    $user->forceFill(['status' => AccountStatus::Suspended])->save();
}

/**
 * The remember-me cookie Laravel would have issued to the account.
 *
 * @return array{string, string}
 */
function rememberMeCookieFor(User $user): array
{
    /** @var SessionGuard $sessionGuard */
    $sessionGuard = Auth::guard('web');

    return [
        $sessionGuard->getRecallerName(),
        $user->id.'|'.$user->getRememberToken().'|'.$user->getAuthPassword(),
    ];
}

it('ends an open web session on the next request after the suspension', function (): void {
    $user = createUser();
    $this->actingAs($user);
    suspendSignedInAccount($user);

    $this->get(route('home'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);

    $this->assertGuest();
});

it('does not let a remember-me cookie sign a suspended account back in', function (): void {
    $user = createUser();
    $user->setRememberToken('remember-me-token-of-a-suspended-account');
    $user->save();
    suspendSignedInAccount($user);

    [$cookieName, $cookieValue] = rememberMeCookieFor($user);

    $this->withCookie($cookieName, $cookieValue)
        ->get(route('home'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('answers a JSON request of a suspended account with 403 and does not perform it', function (): void {
    $post = createPost();
    $user = createUser();
    $this->actingAs($user);
    suspendSignedInAccount($user);

    $this->postJson(route('posts.like', $post))
        ->assertForbidden()
        ->assertExactJson(['message' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);

    expect($post->likes()->count())->toBe(0);
    $this->assertGuest();
});

it('regenerates the CSRF token when it ends the session', function (): void {
    $user = createUser();
    $this->actingAs($user);
    Session::start();
    $previousToken = Session::token();
    suspendSignedInAccount($user);

    $this->get(route('home'));

    expect(Session::token())->not->toBe($previousToken);
});

it('lets an active account and a guest through', function (): void {
    $this->get(route('login'))->assertOk();

    $user = createUser();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});
