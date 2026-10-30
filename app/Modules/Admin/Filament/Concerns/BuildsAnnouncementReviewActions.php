<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use App\Modules\Announcement\Services\AnnouncementService;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use LogicException;

trait BuildsAnnouncementReviewActions
{
    /**
     * @return array<Action>
     */
    protected static function announcementReviewActions(): array
    {
        return [
            self::submitAnnouncementForReviewAction(),
            self::approveAnnouncementAction(),
            self::rejectAnnouncementAction(),
        ];
    }

    /**
     * Load the last submission once for each table row when deciding which actions to show.
     *
     * @param  Relation<*, *, *>  $reviewQuery
     */
    protected static function constrainLatestSubmissionReview(Relation $reviewQuery): void
    {
        $reviewQuery
            ->whereIn('action', [
                AnnouncementReviewAction::Submitted->value,
                AnnouncementReviewAction::Resubmitted->value,
            ])
            ->reorder()
            ->latest()
            ->orderByDesc('id')
            ->limit(1);
    }

    private static function submitAnnouncementForReviewAction(): Action
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
                    $action->failure();

                    return;
                }

                $action->success();
            });
    }

    private static function approveAnnouncementAction(): Action
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

    private static function rejectAnnouncementAction(): Action
    {
        return Action::make('reject')
            ->label(TranslatedText::of('admin/announcements.actions.reject.label'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Announcement $record): bool => self::canReviewAnnouncement($record))
            ->modalHeading(TranslatedText::of('admin/announcements.actions.reject.modal_heading'))
            ->modalSubmitActionLabel(TranslatedText::of('admin/announcements.actions.reject.submit'))
            ->successNotificationTitle(TranslatedText::of('admin/announcements.notifications.rejected.title'))
            ->failureNotificationTitle(TranslatedText::of('admin/announcements.actions.review_action_failed'))
            ->schema([
                Hidden::make('expected_submission_review_id')
                    ->default(fn (Announcement $record): ?int => self::latestSubmissionReview($record)?->id)
                    ->required(),
                Textarea::make('comment')
                    ->label(TranslatedText::of('admin/announcements.actions.review_comment'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(4),
            ])
            ->action(function (array $data, Action $action, Announcement $record, AnnouncementService $announcementService): void {
                $user = self::reviewAdministrator();
                Gate::forUser($user)->authorize('review', $record);

                $expectedSubmissionReviewId = self::expectedSubmissionReviewId($data);
                $comment = self::requiredComment($data);

                try {
                    $announcementService->reject($record, $user, $expectedSubmissionReviewId, $comment);
                } catch (LogicException) {
                    $action->failure();

                    return;
                }

                $action->success();
            });
    }

    private static function canSubmitAnnouncement(Announcement $announcement): bool
    {
        $administrator = Filament::auth()->user();

        return $administrator instanceof User
            && $administrator->can(AdminPermission::AdminAnnouncementsUpdate->value)
            && $announcement->created_by === $administrator->id
            && \in_array($announcement->status, [AnnouncementStatus::Draft, AnnouncementStatus::Rejected], true);
    }

    private static function canReviewAnnouncement(Announcement $announcement): bool
    {
        $administrator = Filament::auth()->user();

        if (! $administrator instanceof User
            || ! $administrator->can(AdminPermission::AdminAnnouncementsReview->value)
            || $announcement->status !== AnnouncementStatus::InReview
            || $announcement->created_by === $administrator->id) {
            return false;
        }

        $latestSubmissionReview = self::latestSubmissionReview($announcement);

        return $latestSubmissionReview instanceof AnnouncementReview
            && $latestSubmissionReview->reviewed_by !== $administrator->id;
    }

    private static function latestSubmissionReview(Announcement $announcement): ?AnnouncementReview
    {
        if (! $announcement->relationLoaded('reviews')) {
            $announcement->load([
                'reviews' => static function (Relation $reviewQuery): void {
                    self::constrainLatestSubmissionReview($reviewQuery);
                },
            ]);
        }

        return $announcement->reviews->first();
    }

    private static function reviewAdministrator(): User
    {
        $authenticatedUser = Filament::auth()->user();

        abort_unless($authenticatedUser instanceof User, 403);

        return $authenticatedUser;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function expectedSubmissionReviewId(array $data): int
    {
        $expectedSubmissionReviewId = filter_var($data['expected_submission_review_id'] ?? null, FILTER_VALIDATE_INT);

        abort_unless(\is_int($expectedSubmissionReviewId) && $expectedSubmissionReviewId > 0, 422);

        return $expectedSubmissionReviewId;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function optionalComment(array $data): ?string
    {
        $comment = $data['comment'] ?? null;

        abort_unless($comment === null || \is_string($comment), 422);

        return \is_string($comment) && trim($comment) !== '' ? $comment : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function requiredComment(array $data): string
    {
        $comment = $data['comment'] ?? null;

        abort_unless(\is_string($comment), 422);

        return $comment;
    }
}
