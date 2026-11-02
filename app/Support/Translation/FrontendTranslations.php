<?php

declare(strict_types=1);

namespace App\Support\Translation;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Translation\Translator;
use InvalidArgumentException;

/**
 * The translation groups of `lang/` that the current page sends to React. A controller registers the groups
 * it renders with `use()`, and `HandleInertiaRequests` shares `toArray()`. Each group is merged over the
 * fallback locale, so a key missing in the active language reaches React in the fallback one.
 */
#[Scoped]
final class FrontendTranslations
{
    /**
     * @var list<string>
     */
    private array $groups = [];

    public function __construct(private readonly Translator $translator) {}

    /**
     * Register translation groups, such as `setting/trusted-devices`, for the current request.
     */
    public function use(string ...$groups): static
    {
        foreach ($groups as $group) {
            if (! \in_array($group, $this->groups, true)) {
                $this->groups[] = $group;
            }
        }

        return $this;
    }

    /**
     * The texts of every registered group in the active locale, keyed by group.
     *
     * @return array<string, array<array-key, mixed>>
     *
     * @throws InvalidArgumentException
     */
    public function toArray(): array
    {
        $translations = [];

        foreach ($this->groups as $group) {
            $translations[$group] = $this->groupTexts($group);
        }

        return $translations;
    }

    /**
     * The group in the active locale over the same group in the fallback locale, merged key by key.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function groupTexts(string $group): array
    {
        $fallbackTexts = $this->textsIn($group, $this->translator->getFallback());
        $localeTexts = $this->textsIn($group, $this->translator->getLocale());

        throw_if($fallbackTexts === null && $localeTexts === null, InvalidArgumentException::class, "The translation group [{$group}] does not exist.");

        return array_replace_recursive($fallbackTexts ?? [], $localeTexts ?? []);
    }

    /**
     * The texts of a group in one locale, or null when that locale has no such group. A missing group
     * loads as an empty array, and a key that names a text instead of a group returns that text.
     *
     * @return array<array-key, mixed>|null
     */
    private function textsIn(string $group, string $locale): ?array
    {
        $texts = $this->translator->get($group, [], $locale, false);

        return \is_array($texts) && $texts !== [] ? $texts : null;
    }
}
