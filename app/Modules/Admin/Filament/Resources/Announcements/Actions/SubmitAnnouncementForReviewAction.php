<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Actions;

use App\Modules\Admin\Filament\Concerns\SupportsAnnouncementReviewActions;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Services\AnnouncementService;
use App\Support\Translation\TranslatedText;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use LogicException;

class SubmitAnnouncementForReviewAction
{
    use SupportsAnnouncementReviewActions;

    public static function make(): Action
    {
        return Action::make('submitForReview')
            ->label(TranslatedText::of('admin/announcements.actions.submit_for_review.label'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(fn (Announcement $record): bool => self::canSubmitAnnouncement($record))
            ->requiresConfirmation()
            ->modalHeading(TranslatedText::of('admin/announcements.actions.submit_for_review.modal_heading'))
            ->modalDescription(TranslatedText::of('admin/announcements.actions.submit_for_review.modal_description'))
            ->modalSubmitActionLabel(TranslatedText::of('admin/announcements.actions.submit_for_review.submit'))
            ->successNotificationTitle(TranslatedText::of('admin/announcements.notifications.submitted.title'))
            ->failureNotificationTitle(TranslatedText::of('admin/announcements.actions.review_action_failed'))
            ->action(function (Action $action, Announcement $record, AnnouncementService $announcementService): void {
                $user = self::reviewAdministrator();
                Gate::forUser($user)->authorize('submit', $record);

                try {
                    $announcementService->submitForReview($record, $user);
                } catch (LogicException) {
                    $record->refresh();
                    $action->failure();

                    return;
                }

                $record->refresh();
                $action->success();
            });
    }
}
