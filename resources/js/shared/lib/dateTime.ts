import dayjs from "dayjs";

import type { Locale } from "../types";
import type { TranslationReplacements } from "./translation";

/**
 * The language a date is formatted in and the translator that reads the `dates` catalog of `lang/`.
 * Components get it already built from `useDateTime()`.
 */
export interface DateTimeContext {
  locale: Locale;
  t: (key: string, replacements?: TranslationReplacements) => string;
}

/**
 * Formats an ISO date string into a long, human-readable date with its time.
 * Example: "15 de enero del 2025, 3:45 PM" / "January 15, 2025 at 3:45 PM".
 *
 * @param iso The date as an ISO string, or null when there is none.
 * @param context The language and texts to format with.
 * @returns The formatted date, or the catalog's "never" text when the date is null.
 */
export function formatLongDate(iso: string | null, context: DateTimeContext): string {
  if (iso === null) {
    return context.t("dates.never");
  }

  return dayjs(iso).locale(context.locale).format(context.t("dates.formats.long"));
}

/**
 * Formats an ISO date string into a compact date.
 * Example: "15 ene 2025" / "Jan 15, 2025".
 *
 * @param iso The date as an ISO string, or null when there is none.
 * @param context The language and texts to format with.
 * @returns The formatted date, or the catalog's "never" text when the date is null.
 */
export function formatShortDate(iso: string | null, context: DateTimeContext): string {
  if (iso === null) {
    return context.t("dates.never");
  }

  return dayjs(iso).locale(context.locale).format(context.t("dates.formats.short"));
}

/**
 * Formats an ISO date string into a full date without the time.
 * Example: "15 de enero del 2025" / "January 15, 2025".
 *
 * @param iso The date as an ISO string, or null when there is none.
 * @param context The language and texts to format with.
 * @returns The formatted date, or the catalog's "never" text when the date is null.
 */
export function formatFullDate(iso: string | null, context: DateTimeContext): string {
  if (iso === null) {
    return context.t("dates.never");
  }

  return dayjs(iso).locale(context.locale).format(context.t("dates.formats.full"));
}

/**
 * Formats an ISO date string into the activation date format (no time).
 * Example: "15 de marzo de 2024" / "March 15, 2024".
 *
 * @param iso The date as an ISO string, or null when there is none.
 * @param context The language and texts to format with.
 * @returns The formatted date, or the catalog's "not available" text when the date is null.
 */
export function formatActivationDate(iso: string | null, context: DateTimeContext): string {
  if (iso === null) {
    return context.t("dates.not_available");
  }

  return dayjs(iso).locale(context.locale).format(context.t("dates.formats.activation_date"));
}

/**
 * Formats an ISO date string into a 12-hour time with a GMT offset suffix.
 * Example: "11:45 AM (GMT-6)" / "11:45 AM (GMT+5:30)".
 *
 * @param iso The date as an ISO string, or null when there is none.
 * @param context The language and texts to format with.
 * @returns The formatted time, or the catalog's "not available" text when the date is null.
 */
export function formatActivationTime(iso: string | null, context: DateTimeContext): string {
  if (iso === null) {
    return context.t("dates.not_available");
  }

  const date = dayjs(iso).locale(context.locale);
  const offset = date.format("Z");
  const formattedOffset = offset.replace(/^([+-])0/, "$1").replace(":00", "");

  return context.t("dates.time_with_offset", {
    offset: formattedOffset,
    time: date.format(context.t("dates.formats.activation_time")),
  });
}

/**
 * Returns the time remaining until a given date in relative format.
 * Example: "en 2 días" / "in 2 days".
 *
 * @param iso The date as an ISO string, or null when there is none.
 * @param context The language and texts to format with.
 * @returns The relative time, or the catalog's "expired" text when the date is null or has passed.
 */
export function formatTimeUntil(iso: string | null, context: DateTimeContext): string {
  if (iso === null || dayjs(iso).isBefore(dayjs())) {
    return context.t("dates.expired");
  }

  return dayjs(iso).locale(context.locale).fromNow();
}

/**
 * Returns the time elapsed since a given date in relative format.
 * Example: "hace 2 horas" / "2 hours ago".
 *
 * @param iso The date as an ISO string, or null when there is none.
 * @param context The language and texts to format with.
 * @returns The relative time, or the catalog's lowercase "never" text when the date is null.
 */
export function fromNow(iso: string | null, context: DateTimeContext): string {
  if (iso === null) {
    return context.t("dates.never_lowercase");
  }

  return dayjs(iso).locale(context.locale).fromNow();
}

/**
 * Returns the ISO string, or the current time as ISO if it is missing.
 *
 * @example
 * valueOrNow("2025-01-15T10:00:00.000Z")  // "2025-01-15T10:00:00.000Z"
 * valueOrNow("")                          // "<current ISO>"
 * valueOrNow(null)                        // "<current ISO>"
 * valueOrNow(undefined)                   // "<current ISO>"
 */
export function valueOrNow(value: string | null | undefined): string {
  if (value === null || value === undefined || value === "") {
    return new Date().toISOString();
  }

  return value;
}
