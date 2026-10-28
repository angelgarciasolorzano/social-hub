<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Data;

use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * The content and schedule of an announcement as the admin form submits it. Validation lives in
 * the form; this object only normalizes the state and maps it to the model columns.
 */
final class AnnouncementData extends Data
{
    public function __construct(
        public string $title,
        public string $body,
        public AnnouncementType $type,
        public AnnouncementAudience $audience,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public bool $isDismissible,
        public ?string $buttonLabel,
        public ?string $buttonUrl,
        public AnnouncementDisplay $display = AnnouncementDisplay::Toast,
    ) {}

    /**
     * Build the data from the admin form state. The button is dropped when `has_button` is off,
     * even if its fields still hold text from before it was switched off; a missing display is a toast.
     *
     * @param  array<string, mixed>  $state
     *
     * @throws InvalidArgumentException when a required field is missing or invalid
     */
    public static function fromFormState(array $state): self
    {
        $fluent = new Fluent($state);
        $hasButton = $fluent->boolean('has_button');

        return new self(
            title: trim($fluent->string('title')->toString()),
            body: $fluent->string('body')->toString(),
            type: self::enumFrom($fluent, 'type', AnnouncementType::class),
            audience: self::enumFrom($fluent, 'audience', AnnouncementAudience::class),
            startsAt: $fluent->date('starts_at')?->toImmutable()
                ?? throw new InvalidArgumentException('The announcement needs a start date.'),
            endsAt: $fluent->date('ends_at')?->toImmutable(),
            isDismissible: $fluent->boolean('is_dismissible', true),
            buttonLabel: $hasButton ? self::nullableString($fluent, 'button_label') : null,
            buttonUrl: $hasButton ? self::nullableString($fluent, 'button_url') : null,
            display: blank($fluent->get('display'))
                ? AnnouncementDisplay::Toast
                : self::enumFrom($fluent, 'display', AnnouncementDisplay::class),
        );
    }

    /**
     * The model columns this data fills.
     *
     * @return array{title: string, body: string, type: AnnouncementType, audience: AnnouncementAudience, display: AnnouncementDisplay, starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, is_dismissible: bool, button_label: string|null, button_url: string|null}
     */
    public function toModelAttributes(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'audience' => $this->audience,
            'display' => $this->display,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'is_dismissible' => $this->isDismissible,
            'button_label' => $this->buttonLabel,
            'button_url' => $this->buttonUrl,
        ];
    }

    /**
     * Read an enum field that may arrive as its value or, when the form was filled from a model, as the case itself.
     *
     * @template TEnum of BackedEnum
     *
     * @param  Fluent<string, mixed>  $fluent
     * @param  class-string<TEnum>  $enumClass
     * @return TEnum
     *
     * @throws InvalidArgumentException when the field is missing or not a case of the enum
     */
    private static function enumFrom(Fluent $fluent, string $key, string $enumClass): BackedEnum
    {
        $value = $fluent->get($key);

        if ($value instanceof $enumClass) {
            return $value;
        }

        return $fluent->enum($key, $enumClass)
            ?? throw new InvalidArgumentException(\sprintf('The announcement field "%s" is not valid.', $key));
    }

    /**
     * Read a trimmed string field, treating blank values as missing.
     *
     * @param  Fluent<string, mixed>  $fluent
     */
    private static function nullableString(Fluent $fluent, string $key): ?string
    {
        $value = trim($fluent->string($key)->toString());

        return $value === '' ? null : $value;
    }
}
