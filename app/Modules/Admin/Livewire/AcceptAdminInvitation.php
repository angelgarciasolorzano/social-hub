<?php

declare(strict_types=1);

namespace App\Modules\Admin\Livewire;

use App\Modules\Admin\Models\AdminInvitation;
use App\Modules\Admin\Services\AdminInvitationService;
use App\User\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

#[Layout('layouts.blatui-livewire')]
final class AcceptAdminInvitation extends Component
{
    use HasFluentValidation;

    #[Locked]
    public string $token = '';

    #[Locked]
    public string $email = '';

    #[Locked]
    public bool $isInvitationValid = false;

    #[Locked]
    public bool $requiresRegistration = false;

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token, AdminInvitationService $adminInvitationService): void
    {
        $this->token = $token;

        $this->loadInvitationState($adminInvitationService);
    }

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        if (! $this->requiresRegistration) {
            return [];
        }

        return [
            'name' => FluentRule::string('Name')->required()->max(255),
            'password' => FluentRule::password(label: 'Password')->required()->confirmed(),
        ];
    }

    public function accept(AdminInvitationService $adminInvitationService): void
    {
        if (! $this->isInvitationValid) {
            return;
        }

        if ($this->requiresRegistration) {
            $this->validate();
        }

        try {
            $adminInvitationService->accept(
                $this->token,
                $this->requiresRegistration ? $this->name : null,
                $this->requiresRegistration ? $this->password : null,
            );
        } catch (ValidationException $validationException) {
            throw_unless(
                \array_key_exists('invitation', $validationException->errors()),
                fn (): ValidationException => $validationException,
            );

            $this->isInvitationValid = false;
            $this->requiresRegistration = false;
            $this->reset(['password', 'password_confirmation']);
            $this->resetValidation();

            return;
        }

        $this->reset(['password', 'password_confirmation']);
        $this->redirectRoute('filament.admin.auth.login');
    }

    public function render(): View
    {
        return view('admin::invitations.accept-admin-invitation');
    }

    private function loadInvitationState(AdminInvitationService $adminInvitationService): void
    {
        $adminInvitation = $adminInvitationService->findValidInvitation($this->token);

        if (! $adminInvitation instanceof AdminInvitation) {
            return;
        }

        $this->email = $adminInvitation->email;

        if ($adminInvitation->targetUser instanceof User) {
            $this->isInvitationValid = true;

            return;
        }

        $existingUser = User::query()
            ->where('email', $adminInvitation->email)
            ->first();

        if ($existingUser instanceof User) {
            $this->isInvitationValid = $existingUser->hasVerifiedEmail();

            return;
        }

        $this->requiresRegistration = true;
        $this->isInvitationValid = true;
    }
}
