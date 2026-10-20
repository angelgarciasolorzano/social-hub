<?php declare(strict_types=1); ?>

<div class="bg-muted/30 flex min-h-svh items-center justify-center px-4 py-10 sm:px-6">
    @if (! $isInvitationValid)
        <x-ui.card variant="sectioned" class="w-full max-w-lg shadow-md">
            <x-ui.card-header class="gap-4">
                <div class="flex items-center gap-4">
                    <div class="bg-destructive/10 text-destructive flex size-12 shrink-0 items-center justify-center rounded-full">
                        <x-lucide-triangle-alert class="size-6" aria-hidden="true" />
                    </div>

                    <div class="min-w-0 space-y-2">
                        <p class="text-destructive text-sm font-medium">{{ __('Administrator invitation') }}</p>
                        <h1 id="invitation-unavailable-title" class="text-2xl font-semibold tracking-tight">
                            {{ __('Invitation unavailable') }}
                        </h1>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content>
                <x-ui.alert variant="destructive">
                    <x-lucide-triangle-alert aria-hidden="true" />
                    <x-ui.alert-description>
                        {{ __('This invitation is invalid, expired, already accepted, or no longer available for this account.') }}
                    </x-ui.alert-description>
                </x-ui.alert>
            </x-ui.card-content>
        </x-ui.card>
    @else
        <x-ui.card variant="sectioned" class="w-full max-w-lg shadow-md">
            <x-ui.card-header class="gap-4">
                <div class="flex items-center gap-4">
                    <div class="bg-success/10 text-success flex size-12 shrink-0 items-center justify-center rounded-full">
                        <x-lucide-shield-check class="size-6" aria-hidden="true" />
                    </div>

                    <div class="min-w-0 space-y-2">
                        <p class="text-primary text-sm font-medium">{{ __('Administrator invitation') }}</p>
                        <h1 id="invitation-title" class="text-2xl font-semibold tracking-tight">
                            {{ __('Set up administrator access') }}
                        </h1>
                    </div>
                </div>

                <div class="bg-muted/60 flex items-center gap-3 rounded-lg p-4">
                    <span class="bg-background text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-full shadow-xs">
                        <x-lucide-mail class="size-5" aria-hidden="true" />
                    </span>

                    <dl class="min-w-0 space-y-1">
                        <dt class="text-muted-foreground text-xs font-medium">{{ __('Invitation email') }}</dt>
                        <dd class="text-foreground text-sm font-semibold break-all">{{ $email }}</dd>
                    </dl>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="space-y-5">
                <x-ui.alert tone="info" role="note">
                    <x-lucide-shield-check aria-hidden="true" />
                    <x-ui.alert-description class="text-foreground">
                        @if ($requiresRegistration)
                            {{ __('This invitation is linked to the email above. Your new account will receive administrator access after acceptance.') }}
                        @else
                            {{ __('Your existing account credentials will remain unchanged.') }}
                        @endif
                    </x-ui.alert-description>
                </x-ui.alert>

                <form wire:submit="accept" class="space-y-5">
                    @if ($requiresRegistration)
                        <div class="space-y-2">
                            <label for="name" class="text-sm font-medium">{{ __('Full name') }}</label>

                            <x-ui.input
                                id="name"
                                name="name"
                                autocomplete="name"
                                wire:model="name"
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                                @if($errors->has('name')) aria-describedby="name-error" @endif
                                required
                            />

                            @error('name')
                                <p id="name-error" role="alert" class="text-destructive text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="password" class="text-sm font-medium">{{ __('Password') }}</label>

                            <x-ui.input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                wire:model="password"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                @if($errors->has('password')) aria-describedby="password-error" @endif
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
                                @if($errors->has('password')) aria-describedby="password-error" @endif
                                required
                            />
                        </div>
                    @endif

                    <x-ui.button
                        type="submit"
                        class="w-full cursor-pointer"
                        wire:target="accept"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="accept">
                            {{ $requiresRegistration ? __('Create account and accept invitation') : __('Accept invitation') }}
                        </span>

                        <span wire:loading wire:target="accept" class="inline-flex items-center gap-2">
                            <x-lucide-loader-circle class="size-4 animate-spin" aria-hidden="true" />
                            {{ __('Processing invitation…') }}
                        </span>

                        <x-slot:after>
                            <span wire:loading.remove wire:target="accept">
                                <x-lucide-arrow-right class="size-4" aria-hidden="true" />
                            </span>
                        </x-slot:after>
                    </x-ui.button>
                </form>
            </x-ui.card-content>
        </x-ui.card>
    @endif
</div>
