<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Database\Factories;

use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Announcement>
     */
    #[Override]
    protected $model = Announcement::class;

    /**
     * Define the model's default state: an active, dismissible announcement for everyone, without a button.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<Announcement>, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'type' => fake()->randomElement(AnnouncementType::cases()),
            'audience' => AnnouncementAudience::All,
            'starts_at' => now()->subDays(random_int(1, 5)),
            'ends_at' => now()->addDays(random_int(1, 10)),
            'button_label' => null,
            'button_url' => null,
            'is_dismissible' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * An announcement that starts in the future.
     */
    public function scheduled(): static
    {
        return $this->state(function (): array {
            $startsAt = now()->addDays(random_int(1, 5));

            return [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addDays(random_int(1, 10)),
            ];
        });
    }

    /**
     * An announcement that already started and has not ended yet.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDays(random_int(1, 5)),
            'ends_at' => now()->addDays(random_int(1, 10)),
        ]);
    }

    /**
     * An announcement whose end date already passed.
     */
    public function expired(): static
    {
        return $this->state(function (): array {
            $endsAt = now()->subDays(random_int(1, 5));

            return [
                'starts_at' => $endsAt->subDays(random_int(1, 10)),
                'ends_at' => $endsAt,
            ];
        });
    }

    /**
     * An announcement with the given type.
     */
    public function ofType(AnnouncementType $announcementType): static
    {
        return $this->state(fn (): array => [
            'type' => $announcementType,
        ]);
    }

    /**
     * An announcement with a call-to-action button.
     */
    public function withButton(): static
    {
        return $this->state(fn (): array => [
            'button_label' => 'Más información',
            'button_url' => fake()->url(),
        ]);
    }

    /**
     * An announcement the user cannot dismiss.
     */
    public function notDismissible(): static
    {
        return $this->state(fn (): array => [
            'is_dismissible' => false,
        ]);
    }

    /**
     * An announcement shown to the given audience.
     */
    public function forAudience(AnnouncementAudience $announcementAudience): static
    {
        return $this->state(fn (): array => [
            'audience' => $announcementAudience,
        ]);
    }

    /**
     * An announcement created by the given administrator.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (): array => [
            'created_by' => $user->id,
        ]);
    }
}
