<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;

it('creates an anonymous activity without subject by default', function (): void {
    $activityLog = ActivityLogFactory::new()->createOne();

    expect($activityLog)
        ->log_name->toBe('content')
        ->event->toBe('content.post_created')
        ->causer_id->toBeNull()
        ->subject_id->toBeNull();
});
