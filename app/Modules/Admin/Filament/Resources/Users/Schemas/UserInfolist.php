<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Schemas;

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
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->description('Identity and profile images for this user.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                ImageEntry::make(UserImageType::PROFILE_PICTURE->value())
                                    ->label('Profile picture')
                                    ->state(fn (User $record): string => url($record->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value())))
                                    ->imageSize(64)
                                    ->circular()
                                    ->alt('Profile picture'),
                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Full name')
                                            ->icon(Heroicon::OutlinedUser),
                                        TextEntry::make('email')
                                            ->label('Email address')
                                            ->icon(Heroicon::OutlinedEnvelope),
                                    ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                    ]),
                            ]),
                        TextEntry::make('cover_image_status')
                            ->label('Cover image')
                            ->state(fn (User $record): string => $record->hasMedia(UserImageType::COVER_IMAGE->value()) ? 'Uploaded' : 'Not added')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Uploaded' ? 'success' : 'gray')
                            ->visible(fn (User $record): bool => ! $record->hasMedia(UserImageType::COVER_IMAGE->value())),
                        ImageEntry::make('cover_image')
                            ->label('Cover image')
                            ->state(fn (User $record): ?string => $record->getFirstMedia(UserImageType::COVER_IMAGE->value())?->getFullUrl())
                            ->imageHeight(120)
                            ->imageWidth('100%')
                            ->columnSpanFull()
                            ->alt('Cover image')
                            ->visible(fn (User $record): bool => $record->hasMedia(UserImageType::COVER_IMAGE->value())),
                    ]),
                Section::make('Account activity')
                    ->description('Email verification and sign-in history for this user.')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        TextEntry::make('email_verification_status')
                            ->label('Email verification')
                            ->state(fn (User $record): string => $record->hasVerifiedEmail() ? 'Verified' : 'Not verified')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Verified' ? 'success' : 'warning'),
                        TextEntry::make('email_verified_at')
                            ->label('Verified at')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->dateTime()
                            ->visible(fn (User $record): bool => $record->hasVerifiedEmail()),
                        TextEntry::make('created_at')
                            ->label('Account created')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->since()
                            ->dateTimeTooltip(),
                        TextEntry::make('last_login_at')
                            ->label('Last sign-in')
                            ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                            ->since()
                            ->dateTimeTooltip()
                            ->placeholder('Never signed in'),
                    ])
                    ->columns([
                        'sm' => 2,
                    ]),
            ]);
    }
}
