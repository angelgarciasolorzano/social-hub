<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Schemas;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    use DescribesAccountStatus;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(TranslatedText::of('admin/users.infolist.profile.heading'))
                    ->description(TranslatedText::of('admin/users.infolist.profile.description'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                ImageEntry::make(UserImageType::PROFILE_PICTURE->value())
                                    ->label(TranslatedText::of('admin/users.fields.profile_picture'))
                                    ->state(fn (User $record): string => url($record->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value())))
                                    ->imageSize(64)
                                    ->circular()
                                    ->alt(TranslatedText::of('admin/users.fields.profile_picture')),
                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(TranslatedText::of('admin/users.infolist.full_name'))
                                            ->icon(Heroicon::OutlinedUser),
                                        TextEntry::make('email')
                                            ->label(TranslatedText::of('admin/users.fields.email_address'))
                                            ->icon(Heroicon::OutlinedEnvelope),
                                    ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                    ]),
                            ]),
                        TextEntry::make('cover_image_status')
                            ->label(TranslatedText::of('admin/users.fields.cover_image'))
                            ->state(fn (User $record): string => TranslatedText::of($record->hasMedia(UserImageType::COVER_IMAGE->value())
                                ? 'admin/users.infolist.cover_image_uploaded'
                                : 'admin/users.infolist.cover_image_not_added'))
                            ->icon(Heroicon::OutlinedPhoto)
                            ->badge()
                            ->color(fn (User $record): string => $record->hasMedia(UserImageType::COVER_IMAGE->value()) ? 'success' : 'gray')
                            ->visible(fn (User $record): bool => ! $record->hasMedia(UserImageType::COVER_IMAGE->value())),
                        ImageEntry::make('cover_image')
                            ->label(TranslatedText::of('admin/users.fields.cover_image'))
                            ->state(fn (User $record): ?string => $record->getFirstMedia(UserImageType::COVER_IMAGE->value())?->getFullUrl())
                            ->imageHeight(120)
                            ->imageWidth('100%')
                            ->columnSpanFull()
                            ->alt(TranslatedText::of('admin/users.fields.cover_image'))
                            ->visible(fn (User $record): bool => $record->hasMedia(UserImageType::COVER_IMAGE->value())),
                    ]),
                Section::make(TranslatedText::of('admin/users.infolist.account_activity.heading'))
                    ->description(TranslatedText::of('admin/users.infolist.account_activity.description'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        TextEntry::make('status')
                            ->label(TranslatedText::of('admin/users.account_status.label'))
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->badge()
                            ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                            ->color(fn (AccountStatus $state): string => self::accountStatusColor($state)),
                        TextEntry::make('email_verification_status')
                            ->label(TranslatedText::of('admin/users.fields.email_verification'))
                            ->state(fn (User $record): string => TranslatedText::of($record->hasVerifiedEmail()
                                ? 'admin/users.infolist.verified'
                                : 'admin/users.infolist.not_verified'))
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->badge()
                            ->color(fn (User $record): string => $record->hasVerifiedEmail() ? 'success' : 'warning'),
                        TextEntry::make('email_verified_at')
                            ->label(TranslatedText::of('admin/users.infolist.verified_at'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->dateTime()
                            ->visible(fn (User $record): bool => $record->hasVerifiedEmail()),
                        TextEntry::make('created_at')
                            ->label(TranslatedText::of('admin/users.infolist.created_at'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->since()
                            ->dateTimeTooltip(),
                        TextEntry::make('last_login_at')
                            ->label(TranslatedText::of('admin/users.infolist.last_sign_in'))
                            ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                            ->since()
                            ->dateTimeTooltip()
                            ->placeholder(TranslatedText::of('admin/users.infolist.never_signed_in')),
                    ])
                    ->columns([
                        'sm' => 2,
                    ]),
            ]);
    }
}
