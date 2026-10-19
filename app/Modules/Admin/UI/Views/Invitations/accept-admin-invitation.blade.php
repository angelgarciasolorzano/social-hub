<?php declare(strict_types=1); ?>

<div class="mx-auto flex min-h-svh w-full max-w-lg items-center px-6 py-12">
    @if (! $isInvitationValid)
        <section class="w-full space-y-4" aria-labelledby="invitation-unavailable-title">
            <h1 id="invitation-unavailable-title" class="text-2xl font-semibold">{{ __('Invitation unavailable') }}</h1>

            <p role="status" class="text-muted-foreground">
                {{ __('This invitation is invalid, expired, already accepted, or no longer available for this account.') }}
            </p>
        </section>
    @else
        <section class="w-full space-y-6" aria-labelledby="invitation-title">
            <header class="space-y-2">
                <h1 id="invitation-title" class="text-2xl font-semibold">{{ __('Set up administrator access') }}</h1>

                <p class="text-muted-foreground">
                    {{ __('This invitation is for') }}
                    <strong class="text-foreground">{{ $email }}</strong>
                </p>
            </header>

            <form wire:submit="accept" class="space-y-5">
                @if ($requiresRegistration)
                    <div class="space-y-2">
                        <label for="name" class="text-sm font-medium"> {{ __('Full name') }} </label>

                        <x-ui.input
                            id="name"
                            name="name"
                            autocomplete="name"
                            wire:model="name"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                            required
                        />

                        @error('name')
                            <p id="name-error" role="alert" class="text-destructive text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="text-sm font-medium"> {{ __('Password') }} </label>

                        <x-ui.input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            wire:model="password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            required
                        />

                        @error('password')
                            <p id="password-error" role="alert" class="text-destructive text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="password_confirmation" class="text-sm font-medium">
                            {{ __('Confirm password') }}
                        </label>

                        <x-ui.input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            wire:model="password_confirmation"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            required
                        />
                    </div>
                @else
                    <p class="text-muted-foreground text-sm">
                        {{ __('Your existing account credentials will remain unchanged.') }}
                    </p>
                @endif

                <x-ui.button type="submit" class="w-full" wire:target="accept" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="accept">
                        {{ $requiresRegistration ? __('Create account and accept invitation') : __('Accept invitation') }}
                    </span>

                    <span wire:loading wire:target="accept"> {{ __('Processing invitation…') }} </span>
                </x-ui.button>
            </form>
        </section>
    @endif
</div>
