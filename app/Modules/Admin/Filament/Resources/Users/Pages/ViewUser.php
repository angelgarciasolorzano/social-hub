<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Resources\Users\Widgets\UserActivityOverview;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use App\User\Modules\AccountStatus\Services\AccountStatusService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;
use Override;

class ViewUser extends ViewRecord
{
    #[Override]
    protected static string $resource = UserResource::class;

    /**
     * @return Action[]
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('suspendAccount')
                ->label('Suspend account')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Suspend account')
                ->modalDescription('The account is signed out everywhere, its trusted devices are revoked and it cannot sign in until it is reactivated. Its data is kept.')
                ->modalSubmitActionLabel('Suspend')
                ->successNotificationTitle('Account suspended')
                ->failureNotificationTitle('The account was already suspended; reload the page to see its current status')
                ->authorize('suspend')
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(AccountStatusChange::REASON_MAX_LENGTH)
                        ->rows(4),
                ])
                ->action(fn (array $data, Action $action, AccountStatusService $accountStatusService) => $this->changeAccountStatus($data, $action, $accountStatusService, AccountStatus::Suspended)),
            Action::make('reactivateAccount')
                ->label('Reactivate account')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Reactivate account')
                ->modalDescription('The account can sign in again. Trusted devices revoked by the suspension stay revoked.')
                ->modalSubmitActionLabel('Reactivate')
                ->successNotificationTitle('Account reactivated')
                ->failureNotificationTitle('The account was already active; reload the page to see its current status')
                ->authorize('reactivate')
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(AccountStatusChange::REASON_MAX_LENGTH)
                        ->rows(4),
                ])
                ->action(fn (array $data, Action $action, AccountStatusService $accountStatusService) => $this->changeAccountStatus($data, $action, $accountStatusService, AccountStatus::Active)),
        ];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            UserActivityOverview::class,
        ];
    }

    /**
     * Apply the status change requested from a header action; a change made meanwhile
     * from another tab marks the action as failed so its failure notification is sent.
     *
     * @param  array<mixed>  $data
     */
    private function changeAccountStatus(array $data, Action $action, AccountStatusService $accountStatusService, AccountStatus $accountStatus): void
    {
        $administrator = Filament::auth()->user();
        $model = $this->getRecord();

        $reason = $data['reason'] ?? null;

        abort_unless($administrator instanceof User && $model instanceof User, 403);
        abort_unless(\is_string($reason), 422);

        try {
            match ($accountStatus) {
                AccountStatus::Suspended => $accountStatusService->suspend($model, $administrator, $reason),
                AccountStatus::Active => $accountStatusService->reactivate($model, $administrator, $reason),
            };
        } catch (LogicException) {
            $action->failure();
        }
    }
}
