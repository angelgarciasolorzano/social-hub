import { useCallback } from "react";

import { usePage } from "@inertiajs/react";

import { translate, translateChoice, type TranslationReplacements } from "../lib/translation";
import type { Locale, SharedData } from "../types";

type Translate = (key: string, replacements?: TranslationReplacements) => string;

type TranslateChoice = (
  key: string,
  count: number,
  replacements?: TranslationReplacements,
) => string;

interface UseTranslationReturn {
  locale: Locale;
  t: Translate;
  tChoice: TranslateChoice;
}

/**
 * Translate keys of `lang/` with the groups the current page received, in the language the server applied.
 * Keys are `group.nested.key`, the same ones PHP reads with `TranslatedText::of()`, e.g.
 * `t("setting/trusted-devices.table.open_actions", { device: name })` or `tChoice("…devices_count", total)`.
 * `t` and `tChoice` keep their identity while the translations do not change, so they are safe as hook dependencies.
 *
 * @returns The active locale and the translators bound to the page's translations.
 */
export function useTranslation(): UseTranslationReturn {
  const { locale, translations } = usePage<SharedData>().props;

  const t = useCallback<Translate>(
    (key, replacements) => translate(translations, key, replacements),
    [translations],
  );

  const tChoice = useCallback<TranslateChoice>(
    (key, count, replacements) => translateChoice(translations, key, count, replacements),
    [translations],
  );

  return { locale, t, tChoice };
}
