import { usePage } from "@inertiajs/react";

import { translate, translateChoice, type TranslationReplacements } from "../lib/translation";
import type { Locale, SharedData } from "../types";

interface UseTranslationReturn {
  locale: Locale;
  t: (key: string, replacements?: TranslationReplacements) => string;
  tChoice: (key: string, count: number, replacements?: TranslationReplacements) => string;
}

/**
 * Translate keys of `lang/` with the groups the current page received, in the language the server applied.
 * Keys are `group.nested.key`, the same ones PHP reads with `TranslatedText::of()`, e.g.
 * `t("setting/trusted-devices.table.open_actions", { device: name })` or `tChoice("…devices_count", total)`.
 */
export function useTranslation(): UseTranslationReturn {
  const { locale, translations } = usePage<SharedData>().props;

  return {
    locale,
    t: (key, replacements) => translate(translations, key, replacements),
    tChoice: (key, count, replacements) => translateChoice(translations, key, count, replacements),
  };
}
