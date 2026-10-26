<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\Tables;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\User\Enums\AccountStatus;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
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
                    ->label('Profile picture')
                    ->state(fn (User $record): string => url(
                        $record->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
                    ))
                    ->defaultImageUrl(asset(User::DEFAULT_PROFILE_PICTURE_PATH))
                    ->imageSize(40)
                    ->circular()
                    ->alt(fn (User $record): string => "Profile picture of {$record->name}"),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state))
                    ->sortable(),
                TextColumn::make('email_verified_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('last_login_at')
                    ->dateTime()
                    ->placeholder(static function (): string {
                        $neverLabel = __('messages.Never');

                        return \is_string($neverLabel) ? $neverLabel : 'Never';
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Account status')
                    ->options(self::accountStatusOptions()),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verification')
                    ->nullable(),
                Filter::make('registration_date')
                    ->label('Registration date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
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
                    ->label('Post presence')
                    ->placeholder('All users')
                    ->trueLabel('Has posts')
                    ->falseLabel('No posts')
                    ->queries(
                        true: fn (Builder $builder): Builder => $builder->whereHas('posts'),
                        false: fn (Builder $builder): Builder => $builder->whereDoesntHave('posts'),
                        blank: fn (Builder $builder): Builder => $builder,
                    ),
            ]);
    }
}
