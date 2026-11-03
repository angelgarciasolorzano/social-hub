<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Schemas;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
                Section::make(TranslatedText::of('admin/roles.users.effective_permissions.heading'))
                    ->description(TranslatedText::of('admin/roles.users.effective_permissions.description'))
                    ->icon(Heroicon::OutlinedKey)
                    ->visible(self::canViewRoles(...))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('no_effective_permissions')
                            ->hiddenLabel()
                            ->state(TranslatedText::of('admin/roles.users.effective_permissions.empty'))
                            ->color('gray')
                            ->visible(fn (User $record): bool => self::effectivePermissionLabels($record) === [])
                            ->columnSpanFull(),
                        ...array_map(self::effectivePermissionsEntry(...), self::permissionAreas()),
                    ]),
            ]);
    }

    private static function canViewRoles(): bool
    {
        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Role::class);
    }

    /**
     * The panel areas of the catalog, in catalog order.
     *
     * @return list<string>
     */
    private static function permissionAreas(): array
    {
        return array_values(array_unique(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->area(),
            AdminPermission::cases(),
        )));
    }

    /**
     * The permissions the user gets from their roles in one area, as badges; hidden when there are none.
     */
    private static function effectivePermissionsEntry(string $area): TextEntry
    {
        return TextEntry::make('effective_permissions_'.$area)
            ->label(TranslatedText::of('admin/roles.catalog.areas.'.$area))
            ->state(fn (User $record): array => self::effectivePermissionLabels($record, $area))
            ->badge()
            ->visible(fn (User $record): bool => self::effectivePermissionLabels($record, $area) !== []);
    }

    /**
     * The labels of the cataloged permissions the user holds through any role or directly, without
     * duplicates and in catalog order, optionally only those of one area. Reads the user's loaded
     * roles and permissions, so the section costs a constant number of queries.
     *
     * @return list<string>
     */
    private static function effectivePermissionLabels(User $user, ?string $area = null): array
    {
        $permissionNames = [];

        foreach ($user->getAllPermissions() as $allPermission) {
            if ($allPermission instanceof Permission && $allPermission->guard_name === 'admin') {
                $permissionNames[] = $allPermission->name;
            }
        }

        return array_values(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->label(),
            array_filter(
                AdminPermission::cases(),
                static fn (AdminPermission $adminPermission): bool => \in_array($adminPermission->value, $permissionNames, true)
                    && ($area === null || $adminPermission->area() === $area),
            ),
        ));
    }
}
