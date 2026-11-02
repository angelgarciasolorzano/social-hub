<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Database\Factories;

use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AnnouncementReview>
 */
class AnnouncementReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AnnouncementReview>
     */
    #[Override]
    protected $model = AnnouncementReview::class;

    /**
     * Define a submitted review with a user actor.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<AnnouncementReview>, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'action' => AnnouncementReviewAction::Submitted,
            'comment' => null,
            'reviewed_by' => User::factory(),
        ];
    }
}
