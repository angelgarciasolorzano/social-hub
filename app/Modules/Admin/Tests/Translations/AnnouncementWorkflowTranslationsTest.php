<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use Illuminate\Support\Facades\Lang;

/**
 * Translation keys used by the announcement approval workflow.
 *
 * @return list<string>
 */
function announcementWorkflowTranslationKeys(): array
{
    $keys = [
        'actions.submit_for_review.label',
        'actions.submit_for_review.modal_heading',
        'actions.submit_for_review.modal_description',
        'actions.submit_for_review.submit',
        'actions.approve.label',
        'actions.approve.modal_heading',
        'actions.approve.submit',
        'actions.reject.label',
        'actions.reject.modal_heading',
        'actions.reject.submit',
        'actions.review_action_failed',
        'actions.review_comment',
        'filters.status',
        'form.overlapping_dialogs',
        'form.reapproval_notice_heading',
        'form.reapproval_notice_description',
        'notifications.submitted.title',
        'notifications.submitted.body',
        'notifications.approved.title',
        'notifications.approved.body',
        'notifications.approved.body_with_comment',
        'notifications.rejected.title',
        'notifications.rejected.body',
        'review_history.action',
        'review_history.comment',
        'review_history.actor',
        'tabs.pending_review',
    ];

    foreach (AnnouncementStatus::cases() as $announcementStatus) {
        $keys[] = 'options.status.'.$announcementStatus->value;
    }

    foreach (AnnouncementReviewAction::cases() as $announcementReviewAction) {
        $keys[] = 'review_actions.'.$announcementReviewAction->value;
    }

    return array_values(array_unique($keys));
}

it('provides all workflow text in the requested locale without fallback', function (string $locale): void {
    $missingKeys = array_values(array_filter(
        announcementWorkflowTranslationKeys(),
        static fn (string $key): bool => ! Lang::has("admin/announcements.{$key}", $locale, false),
    ));

    expect($missingKeys)->toBe([], "lang/{$locale}/admin/announcements.php is missing workflow keys");

    foreach (announcementWorkflowTranslationKeys() as $key) {
        $text = Lang::get("admin/announcements.{$key}", [], $locale);

        expect($text)->toBeString()->not->toBe('', "admin/announcements.{$key} must not be empty in {$locale}");
    }
})->with([
    'English catalog' => ['en'],
    'Spanish catalog' => ['es'],
]);

it('uses the same placeholders in each workflow catalog', function (): void {
    $englishTexts = translationFileTexts('en', 'admin/announcements');
    $spanishTexts = translationFileTexts('es', 'admin/announcements');

    foreach (announcementWorkflowTranslationKeys() as $key) {
        expect(translationPlaceholders($spanishTexts[$key]))
            ->toBe(translationPlaceholders($englishTexts[$key]), "admin/announcements.{$key} has different placeholders in en and es");
    }
});

it('provides each announcement workflow event in the requested activity log locale', function (string $locale): void {
    $announcementWorkflowEvents = [
        ActivityLogAdminEvent::AnnouncementSubmitted,
        ActivityLogAdminEvent::AnnouncementApproved,
        ActivityLogAdminEvent::AnnouncementRejected,
    ];
    $missingKeys = [];

    foreach ($announcementWorkflowEvents as $announcementWorkflowEvent) {
        $translationKey = 'events.'.$announcementWorkflowEvent->eventName();

        if (! Lang::has("admin/activity-log.{$translationKey}", $locale, false)) {
            $missingKeys[] = $translationKey;

            continue;
        }

        $text = Lang::get("admin/activity-log.{$translationKey}", [], $locale);

        expect($text)->toBeString()->not->toBe('', "admin/activity-log.{$translationKey} must not be empty in {$locale}");
    }

    expect($missingKeys)->toBe([], "lang/{$locale}/admin/activity-log.php is missing announcement workflow event keys");
})->with([
    'English catalog' => ['en'],
    'Spanish catalog' => ['es'],
]);
