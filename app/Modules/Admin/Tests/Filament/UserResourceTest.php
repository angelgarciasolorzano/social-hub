<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Testing\TestsFilters;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use LogicException;

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Filament::setCurrentPanel('admin');
});

it('allows admins with the users reader role to list and search users by name and email', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $user = createUser();
    $user->forceFill([
        'name' => 'Searchable Name Sentinel',
        'email' => 'name-match@example.test',
    ])->save();

    $emailMatch = createUser();
    $emailMatch->forceFill([
        'name' => 'Email Match User',
        'email' => 'searchable-email@example.test',
    ])->save();

    $nonMatch = createUser();
    $nonMatch->forceFill([
        'name' => 'Unrelated User',
        'email' => 'unrelated@example.test',
    ])->save();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertSee($user->name)
        ->assertSee($emailMatch->email)
        ->assertSee($nonMatch->email);

    Livewire::test(ListUsers::class)
        ->set('tableSearch', 'Searchable Name Sentinel')
        ->assertSee($user->name)
        ->assertDontSee($emailMatch->email)
        ->assertDontSee($nonMatch->email);

    Livewire::test(ListUsers::class)
        ->set('tableSearch', 'searchable-email@example.test')
        ->assertSee($emailMatch->email)
        ->assertDontSee($user->name)
        ->assertDontSee($nonMatch->email);
});

it('renders the last login fallback in the active application locale', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $targetUser = createUser();
    $targetUser->forceFill(['last_login_at' => null])->save();

    $this->actingAs($authenticatedUser, 'admin');

    $originalLocale = app()->getLocale();

    try {
        app()->setLocale('en');

        Livewire::test(ListUsers::class)
            ->assertSee($targetUser->name)
            ->assertSee('Never');

        app()->setLocale('es');

        Livewire::test(ListUsers::class)
            ->assertSee($targetUser->name)
            ->assertSee('Nunca');
    } finally {
        app()->setLocale($originalLocale);
    }
});

it('filters users by verification status registration date and post presence alone and together', function (): void {
    $authenticatedUser = UserFactory::new()->unverified()->createOne();
    $authenticatedUser->forceFill(['created_at' => '2025-12-31 23:59:59'])->save();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $user = createUser();
    $user->forceFill(['created_at' => '2026-01-15 23:59:59'])->save();
    createPost($user);

    $verifiedWithoutPost = createUser();
    $verifiedWithoutPost->forceFill(['created_at' => '2026-01-20 23:59:59'])->save();

    $verifiedOutsideDateRangeWithPost = createUser();
    $verifiedOutsideDateRangeWithPost->forceFill(['created_at' => '2026-02-01 00:00:00'])->save();
    createPost($verifiedOutsideDateRangeWithPost);

    $unverifiedWithPost = UserFactory::new()->unverified()->createOne();
    $unverifiedWithPost->forceFill(['created_at' => '2026-01-18 12:00:00'])->save();
    createPost($unverifiedWithPost);

    $this->actingAs($authenticatedUser, 'admin');

    $dateRange = [
        'from' => '2026-01-15',
        'until' => '2026-01-20',
    ];

    Livewire::test(ListUsers::class)
        ->set('tableFilters.email_verified_at', ['value' => true])
        ->assertSee([
            $user->name,
            $verifiedWithoutPost->name,
            $verifiedOutsideDateRangeWithPost->name,
        ])
        ->assertDontSee([$authenticatedUser->name, $unverifiedWithPost->name]);

    $testable = Livewire::test(ListUsers::class);
    $registrationDatePage = $testable->instance();

    throw_unless($registrationDatePage instanceof ListUsers, LogicException::class, 'The registration-date filter test must resolve the user list page.');

    $filterTable = (new TestsFilters)->filterTable();
    $filterTable->call($testable, 'registration_date', $dateRange);

    $testable
        ->assertSee([
            $user->name,
            $verifiedWithoutPost->name,
            $unverifiedWithPost->name,
        ])
        ->assertDontSee([$authenticatedUser->name, $verifiedOutsideDateRangeWithPost->name]);

    Livewire::test(ListUsers::class)
        ->set('tableFilters.has_posts', ['value' => true])
        ->assertSee([
            $user->name,
            $verifiedOutsideDateRangeWithPost->name,
            $unverifiedWithPost->name,
        ])
        ->assertDontSee([$authenticatedUser->name, $verifiedWithoutPost->name]);

    Livewire::test(ListUsers::class)
        ->set('tableFilters.email_verified_at', ['value' => true])
        ->set('tableFilters.registration_date.from', $dateRange['from'])
        ->set('tableFilters.registration_date.until', $dateRange['until'])
        ->set('tableFilters.has_posts', ['value' => true])
        ->assertSee($user->name)
        ->assertDontSee([
            $authenticatedUser->name,
            $verifiedWithoutPost->name,
            $verifiedOutsideDateRangeWithPost->name,
            $unverifiedWithPost->name,
        ]);

    Livewire::test(ListUsers::class)
        ->set('tableFilters.email_verified_at', ['value' => true])
        ->set('tableFilters.registration_date.from', '2026-03-01')
        ->set('tableFilters.registration_date.until', '2026-03-02')
        ->set('tableFilters.has_posts', ['value' => true])
        ->assertDontSee([
            $authenticatedUser->name,
            $user->name,
            $verifiedWithoutPost->name,
            $verifiedOutsideDateRangeWithPost->name,
            $unverifiedWithPost->name,
        ]);
});

it('preserves search sorting and pagination when user filters are active', function (): void {
    $authenticatedUser = UserFactory::new()->unverified()->createOne();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $users = collect([createUser()]);

    $firstUser = $users->first();

    throw_unless($firstUser instanceof User, LogicException::class, 'The first filtered user fixture must exist.');

    $firstUser->forceFill([
        'name' => 'Filtered User 11',
        'email' => 'filtered-user-11@example.test',
    ])->save();

    for ($index = 10; $index >= 1; $index--) {
        $user = createUser();
        $user->forceFill([
            'name' => sprintf('Filtered User %02d', $index),
            'email' => sprintf('filtered-user-%02d@example.test', $index),
        ])->save();

        $users->push($user);
    }

    $unverifiedUserWithPost = UserFactory::new()->unverified()->createOne();
    createPost($unverifiedUserWithPost);

    $this->actingAs($authenticatedUser, 'admin');

    $sortedUsers = $users->sortBy('name')->values();
    $lastUser = $sortedUsers->last();

    throw_unless($lastUser instanceof User, LogicException::class, 'The filtered user fixture must exist.');

    Livewire::test(ListUsers::class)
        ->set('tableFilters.email_verified_at', ['value' => true])
        ->set('tableFilters.has_posts', ['value' => false])
        ->call('sortTable', 'name', 'asc')
        ->assertSee($sortedUsers->take(10)->pluck('name')->all())
        ->assertDontSee([$lastUser->name, $unverifiedUserWithPost->name])
        ->assertSeeHtmlInOrder($sortedUsers->take(10)->pluck('name')->all())
        ->call('setPage', 2)
        ->assertSee($lastUser->name)
        ->assertDontSee($sortedUsers->take(10)->pluck('name')->all())
        ->set('tableSearch', $lastUser->name)
        ->assertSee($lastUser->name)
        ->assertDontSee($sortedUsers->take(10)->pluck('name')->all())
        ->set('tableSearch', $lastUser->email)
        ->assertSee($lastUser->name)
        ->assertDontSee($sortedUsers->take(10)->pluck('name')->all());
});

it('allows admins with users view permission to open an allowlisted detail without exposing two factor secrets', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $targetUser = createUser();
    $totpSecretSentinel = 'totp-secret-sentinel-4f82';
    $recoveryCodeSentinel = 'recovery-code-sentinel-7b13';

    $targetUser->forceFill([
        'two_factor_secret' => Crypt::encrypt($totpSecretSentinel),
        'two_factor_recovery_codes' => Crypt::encrypt(json_encode(
            [$recoveryCodeSentinel],
            JSON_THROW_ON_ERROR,
        )),
    ])->save();

    $encryptedTotpSecret = $targetUser->getRawOriginal('two_factor_secret');
    $encryptedRecoveryCodes = $targetUser->getRawOriginal('two_factor_recovery_codes');

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(ViewUser::class, ['record' => $targetUser->id])
        ->assertOk();

    $detailPageComponent = $testable->instance();

    throw_unless($detailPageComponent instanceof ViewUser, LogicException::class, 'The detail test must resolve the Filament ViewUser page.');

    $infolist = $detailPageComponent->getSchema('infolist');

    throw_unless($infolist instanceof Schema, LogicException::class, 'The user detail page must expose its infolist schema.');

    $infolistComponents = array_filter(
        $infolist->getFlatComponents(withActions: false, withHidden: true),
        static fn (mixed $component): bool => $component instanceof Component,
    );

    expect(array_keys($infolistComponents))->toBe([
        'name',
        'email',
        'email_verified_at',
        'created_at',
        'last_login_at',
    ])
        ->and($infolistComponents['name']->getState())->toBe($targetUser->name)
        ->and($infolistComponents['email']->getState())->toBe($targetUser->email)
        ->and($infolistComponents['email_verified_at']->getState())->toEqual($targetUser->email_verified_at)
        ->and($infolistComponents['created_at']->getState())->toEqual($targetUser->created_at)
        ->and($infolistComponents['last_login_at']->getState())->toBe($targetUser->last_login_at);

    $serializedResponse = $testable->html();

    expect($serializedResponse)
        ->not->toContain($totpSecretSentinel)
        ->not->toContain($recoveryCodeSentinel)
        ->not->toContain($encryptedTotpSecret)
        ->not->toContain($encryptedRecoveryCodes)
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes');
});

it('denies the users list when the admin lacks users view any permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    $authenticatedUser->givePermissionTo(AdminPermission::UsersView->value);

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ListUsers::class)
        ->assertForbidden();
});

it('denies a user detail when the admin lacks users view permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    $authenticatedUser->givePermissionTo(AdminPermission::UsersViewAny->value);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ViewUser::class, ['record' => $targetUser->id])
        ->assertForbidden();
});

it('rejects direct user create edit and delete requests without mutating users', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $targetUser = createUser();
    $originalName = $targetUser->name;
    $originalEmail = $targetUser->email;
    $originalUserCount = User::query()->count('id');

    $this->actingAs($authenticatedUser, 'admin');

    $this->get('/admin/users/create')->assertNotFound();
    $this->get('/admin/users/'.$targetUser->id.'/edit')->assertNotFound();
    $this->post('/admin/users')
        ->assertMethodNotAllowed();
    $this->patch('/admin/users/'.$targetUser->id, [
        'name' => 'Unauthorized Name Change',
        'email' => 'unauthorized-change@example.test',
    ])
        ->assertMethodNotAllowed();
    $this->delete('/admin/users/'.$targetUser->id)
        ->assertMethodNotAllowed();

    expect(User::query()->count('id'))->toBe($originalUserCount)
        ->and($targetUser->refresh()->name)->toBe($originalName)
        ->and($targetUser->email)->toBe($originalEmail);
});
