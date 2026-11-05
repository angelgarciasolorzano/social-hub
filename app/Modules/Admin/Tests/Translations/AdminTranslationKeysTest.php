<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Lang;

it('has the same keys in Spanish and English', function (string $file): void {
    $englishKeys = array_keys(translationFileTexts('en', $file));
    $spanishKeys = array_keys(translationFileTexts('es', $file));

    expect(array_values(array_diff($englishKeys, $spanishKeys)))
        ->toBe([], "lang/es/{$file}.php is missing keys")
        ->and(array_values(array_diff($spanishKeys, $englishKeys)))
        ->toBe([], "lang/es/{$file}.php has keys that lang/en/{$file}.php does not");
})->with(translationFilesIn('admin'));

it('has no empty texts and the same placeholders in both languages', function (string $file): void {
    $spanishTexts = translationFileTexts('es', $file);

    foreach (translationFileTexts('en', $file) as $key => $englishText) {
        $spanishText = $spanishTexts[$key] ?? '';

        expect(trim($englishText))->not->toBe('', "lang/en/{$file}.php: '{$key}' is empty")
            ->and(trim($spanishText))->not->toBe('', "lang/es/{$file}.php: '{$key}' is empty")
            ->and(translationPlaceholders($spanishText))
            ->toBe(translationPlaceholders($englishText), "{$file}.{$key}: the placeholders differ between en and es");
    }
})->with(translationFilesIn('admin'));

it('names every cataloged activity event and log in both languages', function (string $locale): void {
    $missingKeys = [];

    foreach (ActivityLogEvent::ENUMS as $enumClass) {
        foreach ($enumClass::cases() as $activityLogEvent) {
            $missingKeys[] = 'events.'.$activityLogEvent->eventName();
        }
    }

    foreach (ActivityLogName::cases() as $activityLogName) {
        $missingKeys[] = 'logs.'.$activityLogName->value;
    }

    foreach (array_keys(Relation::morphMap()) as $morphType) {
        $missingKeys[] = 'parties.types.'.$morphType;
    }

    $missingKeys = array_values(array_filter(
        $missingKeys,
        static fn (string $key): bool => ! Lang::has("admin/activity-log.{$key}", $locale, false),
    ));

    expect($missingKeys)->toBe([], "lang/{$locale}/admin/activity-log.php is missing keys");
})->with(['en', 'es']);

it('has labels and descriptions for announcement enum options in both languages', function (string $locale): void {
    $announcementOptionKeys = [];

    foreach (AnnouncementType::cases() as $announcementType) {
        $announcementOptionKeys[] = 'options.type.'.$announcementType->value;
    }

    foreach (AnnouncementAudience::cases() as $announcementAudience) {
        $announcementOptionKeys[] = 'options.audience.'.$announcementAudience->value;
    }

    foreach (AnnouncementDisplay::cases() as $announcementDisplay) {
        $displayKey = 'options.display.'.$announcementDisplay->value;
        $announcementOptionKeys[] = $displayKey;
        $announcementOptionKeys[] = $displayKey.'_description';
    }

    $missingKeys = array_values(array_filter(
        $announcementOptionKeys,
        static fn (string $key): bool => ! Lang::has("admin/announcements.{$key}", $locale, false),
    ));

    expect($missingKeys)->toBe([], "lang/{$locale}/admin/announcements.php is missing enum presentation keys");
})->with(['en', 'es']);

it('names and describes every cataloged role, permission and permission area in both languages', function (string $locale): void {
    $catalogKeys = [];

    foreach (AdminRole::cases() as $adminRole) {
        $catalogKeys[] = 'roles.'.$adminRole->translationKey().'.label';
        $catalogKeys[] = 'roles.'.$adminRole->translationKey().'.description';
    }

    foreach (AdminPermission::cases() as $adminPermission) {
        $catalogKeys[] = 'permissions.'.$adminPermission->translationKey().'.label';
        $catalogKeys[] = 'permissions.'.$adminPermission->translationKey().'.description';
        $catalogKeys[] = 'areas.'.$adminPermission->area();
    }

    $missingKeys = array_values(array_unique(array_filter(
        $catalogKeys,
        static fn (string $key): bool => ! Lang::has("admin/roles.catalog.{$key}", $locale, false),
    )));

    expect($missingKeys)->toBe([], "lang/{$locale}/admin/roles.php is missing catalog keys");
})->with(['en', 'es']);

it('names every role type and describes every authorization failure in both languages', function (string $locale): void {
    $requiredKeys = array_map(
        static fn (string $key): string => 'types.'.$key,
        ['column', 'filter', 'system', 'custom', 'system_tooltip', 'custom_tooltip'],
    );

    foreach (AdminAuthorizationFailure::cases() as $adminAuthorizationFailure) {
        $requiredKeys[] = 'failures.'.$adminAuthorizationFailure->value;
    }

    $missingKeys = array_values(array_filter(
        $requiredKeys,
        static fn (string $key): bool => ! Lang::has("admin/roles.{$key}", $locale, false),
    ));

    expect($missingKeys)->toBe([], "lang/{$locale}/admin/roles.php is missing role type or failure keys");
})->with(['en', 'es']);
