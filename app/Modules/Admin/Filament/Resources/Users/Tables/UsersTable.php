<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Tables;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    use DescribesAccountStatus;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media'))
            ->deferLoading()
            ->loadingSkeleton()
            ->columns([
                ImageColumn::make('profile_picture')
                    ->label(TranslatedText::of('admin/users.fields.profile_picture'))
                    ->state(fn (User $record): string => url(
                        $record->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
                    ))
                    ->defaultImageUrl(asset(User::DEFAULT_PROFILE_PICTURE_PATH))
                    ->imageSize(40)
                    ->circular()
                    ->alt(fn (User $record): string => TranslatedText::of('admin/users.table.profile_picture_alt', ['name' => $record->name])),
                TextColumn::make('name')
                    ->label(TranslatedText::of('admin/users.table.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(TranslatedText::of('admin/users.table.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(TranslatedText::of('admin/common.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state))
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->label(TranslatedText::of('admin/users.table.email_verified_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/users.table.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label(TranslatedText::of('admin/users.table.last_login_at'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/common.never'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(TranslatedText::of('admin/users.account_status.label'))
                    ->options(self::accountStatusOptions()),
                TernaryFilter::make('email_verified_at')
                    ->label(TranslatedText::of('admin/users.fields.email_verification'))
                    ->nullable(),
                Filter::make('registration_date')
                    ->label(TranslatedText::of('admin/users.table.filters.registration_date'))
                    ->schema([
                        DatePicker::make('from')->label(TranslatedText::of('admin/common.filters.from')),
                        DatePicker::make('until')->label(TranslatedText::of('admin/common.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '>=', $date)
                                : $builder,
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '<=', $date)
                                : $builder,
                        )),
                TernaryFilter::make('has_posts')
                    ->label(TranslatedText::of('admin/users.table.filters.post_presence'))
                    ->placeholder(TranslatedText::of('admin/users.table.filters.all_users'))
                    ->trueLabel(TranslatedText::of('admin/users.table.filters.has_posts'))
                    ->falseLabel(TranslatedText::of('admin/users.table.filters.no_posts'))
                    ->queries(
                        true: fn (Builder $builder): Builder => $builder->whereHas('posts'),
                        false: fn (Builder $builder): Builder => $builder->whereDoesntHave('posts'),
                        blank: fn (Builder $builder): Builder => $builder,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye)
                    ->modal(false)
                    ->url(fn (User $user): string => UserResource::getUrl('view', ['record' => $user])),
            ]);
    }
}
