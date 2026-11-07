<?php

declare(strict_types=1);

use Filament\Facades\Filament;

it('warns before leaving a create or edit page of the admin panel with unsaved changes', function (): void {
    expect(Filament::getPanel('admin')->hasUnsavedChangesAlerts())->toBeTrue();
});
