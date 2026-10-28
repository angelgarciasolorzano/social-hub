<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Schemas;

use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AnnouncementForm
{
    /**
     * The largest banner image accepted, in kilobytes.
     */
    public const int IMAGE_MAX_SIZE_KILOBYTES = 2048;

    /**
     * The longest button link accepted, matching its column.
     */
    public const int BUTTON_URL_MAX_LENGTH = 2048;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Content')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(Announcement::TITLE_MAX_LENGTH),
                        RichEditor::make('body')
                            ->label('Message')
                            ->required()
                            ->fileAttachments(false)
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['undo', 'redo'],
                            ]),
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Image')
                            ->collection(Announcement::IMAGE_MEDIA_COLLECTION)
                            ->image()
                            ->maxSize(self::IMAGE_MAX_SIZE_KILOBYTES),
                    ]),
                Section::make('Display')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(self::typeOptions())
                            ->default(AnnouncementType::Info->value)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                if (! $get->boolean('display_was_chosen')) {
                                    $set('display', self::suggestedDisplay($state)->value);
                                }
                            }),
                        Select::make('audience')
                            ->label('Audience')
                            ->options(self::audienceOptions())
                            ->default(AnnouncementAudience::All->value)
                            ->required(),
                        Radio::make('display')
                            ->label('Show as')
                            ->options(self::displayOptions())
                            ->descriptions(self::displayDescriptions())
                            ->default(AnnouncementDisplay::Toast->value)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('display_was_chosen', true);
                            })
                            ->hint(fn (Get $get, ?Announcement $record): ?string => self::overlappingDialogsHint($get, $record))
                            ->hintIcon(fn (Get $get, ?Announcement $record): ?Heroicon => self::overlappingDialogsHint($get, $record) === null ? null : Heroicon::ExclamationTriangle)
                            ->hintColor('warning'),
                        Hidden::make('display_was_chosen')
                            ->default(false)
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Hidden $hidden, ?Announcement $record): void {
                                $hidden->state($record instanceof Announcement);
                            }),
                        DateTimePicker::make('starts_at')
                            ->label('Starts at')
                            ->default(now())
                            ->required()
                            ->live(onBlur: true),
                        DateTimePicker::make('ends_at')
                            ->label('Ends at')
                            ->helperText('Leave empty to keep it until it is unpublished.')
                            ->after('starts_at')
                            ->live(onBlur: true),
                        Toggle::make('is_dismissible')
                            ->label('Users can dismiss it')
                            ->default(true),
                    ]),
                Section::make('Button')
                    ->columns(2)
                    ->schema([
                        Toggle::make('has_button')
                            ->label('Has button')
                            ->live()
                            ->columnSpanFull()
                            ->afterStateHydrated(function (Toggle $toggle, ?Announcement $record): void {
                                $toggle->state($record?->button_url !== null);
                            }),
                        TextInput::make('button_label')
                            ->label('Button text')
                            ->required()
                            ->maxLength(Announcement::BUTTON_LABEL_MAX_LENGTH)
                            ->visible(fn (Get $get): bool => $get->boolean('has_button')),
                        TextInput::make('button_url')
                            ->label('Button link')
                            ->helperText('An https:// address or a path of this site, such as /setting/two-factor-authentication.')
                            ->required()
                            ->maxLength(self::BUTTON_URL_MAX_LENGTH)
                            ->rule(fn (): Closure => self::buttonUrlRule())
                            ->visible(fn (Get $get): bool => $get->boolean('has_button')),
                    ]),
            ]);
    }

    /**
     * Whether the link is an https address or a path of this site without spaces or control characters;
     * anything else, such as `javascript:`, `//host` or `/\t/host`, could send users somewhere unsafe.
     */
    public static function isAllowedButtonUrl(string $url): bool
    {
        // Browsers drop tabs and line breaks while parsing a URL, so `/\t/evil.com` would become `//evil.com`.
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && ! str_contains($url, '\\');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    /**
     * Validation rule for the button link.
     */
    private static function buttonUrlRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! \is_string($value) || ! self::isAllowedButtonUrl($value)) {
                $fail('The link must start with https:// or be a path of this site starting with /.');
            }
        };
    }

    /**
     * The presentation suggested for a type until the administrator picks one: a dialog for critical
     * announcements, a toast for the rest.
     */
    private static function suggestedDisplay(mixed $type): AnnouncementDisplay
    {
        $announcementType = $type instanceof AnnouncementType ? $type : AnnouncementType::tryFrom(\is_string($type) ? $type : '');

        return $announcementType === AnnouncementType::Critical ? AnnouncementDisplay::Dialog : AnnouncementDisplay::Toast;
    }

    /**
     * A warning about the other dialogs whose schedule crosses this one, so administrators avoid stacking
     * interruptions; it never blocks saving.
     */
    private static function overlappingDialogsHint(Get $get, ?Announcement $record): ?string
    {
        $display = $get('display');
        $isDialog = $display === AnnouncementDisplay::Dialog || $display === AnnouncementDisplay::Dialog->value;
        $startsAt = self::dateFrom($get('starts_at'));

        if (! $isDialog || ! $startsAt instanceof CarbonImmutable) {
            return null;
        }

        $overlappingDialogs = Announcement::query()
            ->dialogsOverlapping($startsAt, self::dateFrom($get('ends_at')), $record?->id)
            ->count();

        return match ($overlappingDialogs) {
            0 => null,
            1 => '1 other dialog is shown in the same period.',
            default => "{$overlappingDialogs} other dialogs are shown in the same period.",
        };
    }

    private static function dateFrom(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return \is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @return array<string, string>
     */
    private static function displayOptions(): array
    {
        $displayOptions = [];

        foreach (AnnouncementDisplay::cases() as $announcementDisplay) {
            $displayOptions[$announcementDisplay->value] = $announcementDisplay->label();
        }

        return $displayOptions;
    }

    /**
     * @return array<string, string>
     */
    private static function displayDescriptions(): array
    {
        $displayDescriptions = [];

        foreach (AnnouncementDisplay::cases() as $announcementDisplay) {
            $displayDescriptions[$announcementDisplay->value] = $announcementDisplay->description();
        }

        return $displayDescriptions;
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        $typeOptions = [];

        foreach (AnnouncementType::cases() as $announcementType) {
            $typeOptions[$announcementType->value] = $announcementType->label();
        }

        return $typeOptions;
    }

    /**
     * @return array<string, string>
     */
    private static function audienceOptions(): array
    {
        $audienceOptions = [];

        foreach (AnnouncementAudience::cases() as $announcementAudience) {
            $audienceOptions[$announcementAudience->value] = $announcementAudience->label();
        }

        return $audienceOptions;
    }
}
