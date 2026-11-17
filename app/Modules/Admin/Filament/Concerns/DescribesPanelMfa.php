<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The panel MFA column and filter shared by the tables of administrators, so the holders of a role and
 * the holders of a permission show and filter it the same way. It reads whether the app-based MFA
 * secret is set, never the secret.
 */
trait DescribesPanelMfa
{
    protected static function panelMfaColumn(): IconColumn
    {
        return IconColumn::make('panel_mfa')
            ->label(TranslatedText::of('admin/roles.relations.users.columns.panel_mfa'))
            ->state(fn (User $record): bool => $record->getRawOriginal('app_authentication_secret') !== null)
            ->boolean()
            ->trueIcon(Heroicon::OutlinedShieldCheck)
            ->falseIcon(Heroicon::OutlinedShieldExclamation)
            ->tooltip(fn (bool $state): string => TranslatedText::of($state
                ? 'admin/roles.relations.users.mfa.configured'
                : 'admin/roles.relations.users.mfa.missing'))
            ->toggleable();
    }

    protected static function panelMfaFilter(): TernaryFilter
    {
        return TernaryFilter::make('panel_mfa')
            ->label(TranslatedText::of('admin/roles.relations.users.filters.panel_mfa'))
            ->placeholder(TranslatedText::of('admin/roles.relations.users.filters.all_holders'))
            ->trueLabel(TranslatedText::of('admin/roles.relations.users.mfa.configured'))
            ->falseLabel(TranslatedText::of('admin/roles.relations.users.mfa.missing'))
            ->queries(
                true: fn (Builder $query): Builder => $query->whereNotNull('app_authentication_secret'),
                false: fn (Builder $query): Builder => $query->whereNull('app_authentication_secret'),
            );
    }
}
