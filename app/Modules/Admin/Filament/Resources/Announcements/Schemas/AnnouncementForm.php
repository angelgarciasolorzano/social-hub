<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Schemas;

use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                            ->required(),
                        Select::make('audience')
                            ->label('Audience')
                            ->options(self::audienceOptions())
                            ->default(AnnouncementAudience::All->value)
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->label('Starts at')
                            ->default(now())
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Ends at')
                            ->helperText('Leave empty to keep it until it is unpublished.')
                            ->after('starts_at'),
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
                            ->helperText('An https:// address or a path of this site, such as /setting/two-factor.')
                            ->required()
                            ->maxLength(self::BUTTON_URL_MAX_LENGTH)
                            ->rule(fn (): Closure => self::buttonUrlRule())
                            ->visible(fn (Get $get): bool => $get->boolean('has_button')),
                    ]),
            ]);
    }

    /**
     * Whether the link is an https address or a path of this site; anything else, such as
     * `javascript:` or a protocol-relative `//host`, could send users somewhere unsafe.
     */
    public static function isAllowedButtonUrl(string $url): bool
    {
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
