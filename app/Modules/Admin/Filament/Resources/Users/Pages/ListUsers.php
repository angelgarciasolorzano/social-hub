<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\Modules\Admin\Services\AdminInvitationService;
use App\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Override;

class ListUsers extends ListRecords
{
    #[Override]
    protected static string $resource = UserResource::class;

    /**
     * @return Action[]
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('inviteAdmin')
                ->label('Invite administrator')
                ->modalHeading('Invite administrator')
                ->modalSubmitActionLabel('Send invitation')
                ->successNotificationTitle('Invitation email queued')
                ->authorize(fn (): bool => $this->canInviteAdministrator())
                ->schema([
                    Select::make('email')
                        ->label('Email address')
                        ->helperText('Search for a registered user or enter a new email address.')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => $this->getInvitationUserSearchResults($search))
                        ->getOptionLabelUsing(fn (?string $value): ?string => $this->getInvitationUserOptionLabel($value))
                        ->createOptionModalHeading('Enter an unregistered email address')
                        ->createOptionForm([
                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(static function (array $data): string {
                            /** @var array{email: string} $validatedData */
                            $validatedData = $data;

                            return $validatedData['email'];
                        }),
                ])
                ->action(function (array $data, AdminInvitationService $adminInvitationService): void {
                    $inviter = Filament::auth()->user();

                    abort_unless($inviter instanceof User, 403);

                    /** @var array{email: string} $validatedData */
                    $validatedData = $data;

                    $adminInvitationService->issue($inviter, $validatedData['email']);
                }),
        ];
    }

    /**
     * @return array<class-string<Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            RegistrationSummaryWidget::class,
        ];
    }

    private function canInviteAdministrator(): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminUsersCreate->value);
    }

    /**
     * @return array<string, string>
     */
    private function getInvitationUserSearchResults(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return User::query()
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->orderBy('email', 'asc')
            ->limit(50)
            ->get(['email', 'name'])
            ->mapWithKeys(static fn (User $user): array => [
                $user->email => "{$user->name} ({$user->email})",
            ])
            ->all();
    }

    private function getInvitationUserOptionLabel(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $user = User::query()
            ->where('email', $email)
            ->first(['email', 'name']);

        if (! $user instanceof User) {
            return $email;
        }

        return "{$user->name} ({$user->email})";
    }
}
