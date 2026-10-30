import type { TranslationTree } from "../types";

/**
 * The translation groups a page received in `props.translations`, keyed by group (`setting/common`).
 */
export type Translations = Record<string, TranslationTree | undefined>;

/**
 * Values for the `:name` placeholders of a text.
 */
export type TranslationReplacements = Record<string, string | number>;

const PLURAL_CONDITION_PATTERN = /^[{[]([^[\]{}]*)[}\]]/;

/**
 * The text of `group.nested.key` with its placeholders replaced, or the key itself when the group or the
 * text does not exist, as Laravel's `__()` does. The server already filled missing keys with the fallback.
 *
 * @param translations The groups the page received in `props.translations`.
 * @param key The key of the text, e.g. `setting/common.actions.cancel`.
 * @param replacements The values of the text's `:name` placeholders.
 * @returns The translated text, or `key` when there is no text for it.
 */
export function translate(
  translations: Translations,
  key: string,
  replacements: TranslationReplacements = {},
): string {
  const text = findText(translations, key);

  return text === undefined ? key : replacePlaceholders(text, replacements);
}

/**
 * The plural form of `group.nested.key` for `count`, with Laravel's `trans_choice` syntax:
 * `{1} one|[2,*] many`, `{0} none|{1} one|[2,*] many` or the plain `one|many`. `:count` is filled in.
 *
 * @param translations The groups the page received in `props.translations`.
 * @param key The key of the plural text, e.g. `setting/trusted-device-dialogs.revoke_all.devices_count`.
 * @param count The quantity that picks the form and fills `:count`.
 * @param replacements The values of the other `:name` placeholders; a `count` here overrides the quantity.
 * @returns The chosen form with its placeholders replaced, or `key` when there is no text for it.
 */
export function translateChoice(
  translations: Translations,
  key: string,
  count: number,
  replacements: TranslationReplacements = {},
): string {
  const text = findText(translations, key);

  if (text === undefined) {
    return key;
  }

  return replacePlaceholders(choosePluralForm(text, count), { count, ...replacements });
}

/**
 * Walk the nested group to the text; the group is everything before the first dot.
 *
 * @param translations The groups the page received in `props.translations`.
 * @param key The key of the text, `group.nested.key`.
 * @returns The text, or `undefined` when the key has no group, is missing or names a group of texts.
 */
function findText(translations: Translations, key: string): string | undefined {
  const separatorIndex = key.indexOf(".");

  if (separatorIndex === -1) {
    return undefined;
  }

  let node: string | TranslationTree | undefined = translations[key.slice(0, separatorIndex)];

  for (const segment of key.slice(separatorIndex + 1).split(".")) {
    if (node === undefined || typeof node === "string") {
      return undefined;
    }

    node = node[segment];
  }

  return typeof node === "string" ? node : undefined;
}

/**
 * Replace `:name`, `:Name` and `:NAME` with the value as is, capitalized and uppercased, like Laravel.
 * Longer placeholders are matched first, so `:counter` is never read as `:count` plus `er`.
 *
 * @param text The text whose placeholders are replaced.
 * @param replacements The value of each placeholder, keyed by its name without the colon.
 * @returns The text with every known placeholder replaced; unknown ones are left as they are.
 */
function replacePlaceholders(text: string, replacements: TranslationReplacements): string {
  const valuesByPlaceholder = new Map<string, string>();

  for (const [name, rawValue] of Object.entries(replacements)) {
    const value = String(rawValue);

    valuesByPlaceholder.set(`:${name}`, value);
    valuesByPlaceholder.set(`:${capitalize(name)}`, capitalize(value));
    valuesByPlaceholder.set(`:${name.toUpperCase()}`, value.toUpperCase());
  }

  if (valuesByPlaceholder.size === 0) {
    return text;
  }

  const placeholders = [...valuesByPlaceholder.keys()]
    .sort((first, second) => second.length - first.length)
    .map((placeholder) => placeholder.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"));

  return text.replace(
    new RegExp(placeholders.join("|"), "g"),
    (placeholder) => valuesByPlaceholder.get(placeholder) ?? placeholder,
  );
}

/**
 * Pick the form whose `{n}`, `{a,b}` or `[from,to]` condition matches `count` (`*` is open-ended).
 * Without a match, English and Spanish use the first form for one and the second for any other count.
 *
 * @param text The plural text, its forms separated by `|`.
 * @param count The quantity that picks the form.
 * @returns The chosen form without its condition, trimmed.
 */
function choosePluralForm(text: string, count: number): string {
  const forms = text.split("|");

  for (const form of forms) {
    const condition = PLURAL_CONDITION_PATTERN.exec(form);

    if (condition?.[1] !== undefined && matchesPluralCondition(condition[1], count)) {
      return form.slice(condition[0].length).trim();
    }
  }

  const plainForms = forms.map((form) => form.replace(PLURAL_CONDITION_PATTERN, "").trim());
  const pluralIndex = count === 1 ? 0 : 1;

  return plainForms[pluralIndex] ?? plainForms[0] ?? text;
}

/**
 * `{1}` and `{0,1}` list exact counts; `[2,*]`, `[*,0]` and `[2,5]` are inclusive ranges.
 *
 * @param condition The condition without its brackets, e.g. `2,*`.
 * @param count The quantity to check.
 * @returns Whether `count` satisfies the condition.
 */
function matchesPluralCondition(condition: string, count: number): boolean {
  const bounds = condition.split(",").map((bound) => bound.trim());

  if (bounds.length !== 2) {
    return bounds.some((bound) => bound !== "" && Number(bound) === count);
  }

  const [from = "", to = ""] = bounds;

  if (to === "*") {
    return count >= Number(from);
  }

  if (from === "*") {
    return count <= Number(to);
  }

  return count >= Number(from) && count <= Number(to);
}

/**
 * Uppercase the first character, as Laravel's `Str::ucfirst()` does for `:Name` placeholders.
 *
 * @param value The text to capitalize.
 * @returns The text with its first character in uppercase.
 */
function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}
