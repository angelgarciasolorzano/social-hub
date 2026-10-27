<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Models;

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A system announcement shown as a banner in the application between its start and end dates.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property AnnouncementType $type
 * @property AnnouncementAudience $audience
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string|null $button_label
 * @property string|null $button_url
 * @property bool $is_dismissible
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, User> $dismissedBy
 *
 * @mixin IdeHelperAnnouncement
 */
#[UseFactory(AnnouncementFactory::class)]
#[Fillable([
    'title',
    'body',
    'type',
    'audience',
    'starts_at',
    'ends_at',
    'button_label',
    'button_url',
    'is_dismissible',
    'created_by',
])]
class Announcement extends Model implements HasMedia
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * The morph name used for polymorphic relations.
     */
    public const string MORPH_NAME = 'announcement';

    /**
     * The media collection that holds the optional banner image.
     */
    public const string IMAGE_MEDIA_COLLECTION = 'announcement_image';

    /**
     * The maximum length of the title, shared by the migration and the admin form.
     */
    public const int TITLE_MAX_LENGTH = 120;

    /**
     * The maximum length of the button label, shared by the migration and the admin form.
     */
    public const int BUTTON_LABEL_MAX_LENGTH = 40;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'audience' => AnnouncementAudience::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_dismissible' => 'boolean',
        ];
    }

    /**
     * Register the media collections of the announcement.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE_MEDIA_COLLECTION)->singleFile();
    }

    /**
     * Get the administrator who created the announcement, null once that account is deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the users who dismissed the announcement.
     *
     * @return BelongsToMany<User, $this>
     */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_dismissals')
            ->withPivot('created_at');
    }
}
