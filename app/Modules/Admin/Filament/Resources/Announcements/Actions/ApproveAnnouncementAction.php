<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Actions;

use App\Modules\Admin\Filament\Concerns\SupportsAnnouncementReviewActions;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Services\AnnouncementService;
use App\Support\Translation\TranslatedText;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ApproveAnnouncementAction
{
    use SupportsAnnouncementReviewActions;

    public static function make(): Action
    {
        return Action::make('approve')
            ->label(TranslatedText::of('admin/announcements.actions.approve.label'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Announcement $record): bool => self::canReviewAnnouncement($record))
            ->modalHeading(TranslatedText::of('admin/announcements.actions.approve.modal_heading'))
            ->modalSubmitActionLabel(TranslatedText::of('admin/announcements.actions.approve.submit'))
            ->successNotificationTitle(TranslatedText::of('admin/announcements.notifications.approved.title'))
            ->failureNotificationTitle(TranslatedText::of('admin/announcements.actions.review_action_failed'))
            ->schema([
                Hidden::make('expected_submission_review_id')
                    ->default(fn (Announcement $record): ?int => self::latestSubmissionReview($record)?->id)
                    ->required(),
                Textarea::make('comment')
                    ->label(TranslatedText::of('admin/announcements.actions.review_comment'))
                    ->maxLength(1000)
                    ->rows(4),
            ])
            ->action(function (array $data, Action $action, Announcement $record, AnnouncementService $announcementService): void {
                $user = self::reviewAdministrator();
                Gate::forUser($user)->authorize('review', $record);

                $expectedSubmissionReviewId = self::expectedSubmissionReviewId($data);
                $comment = self::optionalComment($data);

                try {
                    $announcementService->approve($record, $user, $expectedSubmissionReviewId, $comment);
                } catch (LogicException) {
                    $action->failure();

                    return;
                }

                $action->success();
            });
    }
}
