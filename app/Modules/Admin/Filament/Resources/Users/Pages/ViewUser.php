<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Pages;

use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Resources\Users\Widgets\UserActivityOverview;
use App\Support\Translation\TranslatedText;
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

    private const string SUSPEND_ACTION = 'suspendAccount';

    private const string REACTIVATE_ACTION = 'reactivateAccount';

    /**
     * @return Action[]
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make(self::SUSPEND_ACTION)
                ->label(TranslatedText::of('admin/users.actions.suspend.label'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(TranslatedText::of('admin/users.actions.suspend.modal_heading'))
                ->modalDescription(TranslatedText::of('admin/users.actions.suspend.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/users.actions.suspend.submit'))
                ->successNotificationTitle(TranslatedText::of('admin/users.actions.suspend.success'))
                ->failureNotificationTitle(TranslatedText::of('admin/users.actions.suspend.failure'))
                ->authorize('suspend')
                ->schema([
                    Textarea::make('reason')
                        ->label(TranslatedText::of('admin/common.fields.reason'))
                        ->required()
                        ->maxLength(AccountStatusChange::REASON_MAX_LENGTH)
                        ->rows(4),
                ])
                ->action(fn (array $data, Action $action, AccountStatusService $accountStatusService) => $this->changeAccountStatus($data, $action, $accountStatusService, AccountStatus::Suspended)),
            Action::make(self::REACTIVATE_ACTION)
                ->label(TranslatedText::of('admin/users.actions.reactivate.label'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(TranslatedText::of('admin/users.actions.reactivate.modal_heading'))
                ->modalDescription(TranslatedText::of('admin/users.actions.reactivate.modal_description'))
                ->modalSubmitActionLabel(TranslatedText::of('admin/users.actions.reactivate.submit'))
                ->successNotificationTitle(TranslatedText::of('admin/users.actions.reactivate.success'))
                ->failureNotificationTitle(TranslatedText::of('admin/users.actions.reactivate.failure'))
                ->authorize('reactivate')
                ->schema([
                    Textarea::make('reason')
                        ->label(TranslatedText::of('admin/common.fields.reason'))
                        ->required()
                        ->maxLength(AccountStatusChange::REASON_MAX_LENGTH)
                        ->rows(4),
                ])
                ->action(fn (array $data, Action $action, AccountStatusService $accountStatusService) => $this->changeAccountStatus($data, $action, $accountStatusService, AccountStatus::Active)),
        ];
    }

    /**
     * Filament checks the action's authorization again before running it, and `UserPolicy` denies
     * suspending a suspended account (or reactivating an active one), so when another tab changed the
     * status while this modal was open the call would be dropped without a word. Tell the administrator
     * instead, with the action's own failure notification, and close the modal without writing.
     *
     * @param  array<string, mixed>  $arguments
     */
    #[Override]
    public function callMountedAction(array $arguments = []): mixed
    {
        $action = $this->getMountedAction();

        if ($action instanceof Action && $this->accountAlreadyHasTargetStatus($action)) {
            $action->sendFailureNotification();
            $this->unmountAction();

            return null;
        }

        return parent::callMountedAction($arguments);
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            UserActivityOverview::class,
        ];
    }

    /**
     * Whether the action changes the account status and the account already has the status it sets.
     */
    private function accountAlreadyHasTargetStatus(Action $action): bool
    {
        $targetStatus = match ($action->getName()) {
            self::SUSPEND_ACTION => AccountStatus::Suspended,
            self::REACTIVATE_ACTION => AccountStatus::Active,
            default => null,
        };

        $model = $this->getRecord();

        return $targetStatus instanceof AccountStatus
            && $model instanceof User
            && $model->status === $targetStatus;
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
