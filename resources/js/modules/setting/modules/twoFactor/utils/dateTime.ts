import dayjs from "dayjs";

const LONG_DATE_FORMAT = "D [de] MMMM [del] YYYY, h:mm A";

/**
 * Formats an ISO date string into a long, human-readable format in Spanish.
 * Example: "15 de enero del 2025, 3:45 PM". Returns "Nunca" if the date is null.
 */
export function formatLongDate(iso: string | null): string {
  if (iso === null) {
    return "Nunca";
  }

  return dayjs(iso).format(LONG_DATE_FORMAT);
}

/**
 * Returns the time remaining until a given date in relative format.
 * Example: "hace 5 minutos", "en 2 días". Returns "vencido" if the date has passed or is null.
 */
export function formatTimeUntil(iso: string | null): string {
  if (iso === null || dayjs(iso).isBefore(dayjs())) {
    return "vencido";
  }

  return dayjs(iso).fromNow();
}

/**
 * Returns the time elapsed since a given date in relative format.
 * Example: "hace 2 horas", "hace un día". Returns "nunca" if the date is null.
 */
export function fromNow(iso: string | null): string {
  if (iso === null) {
    return "nunca";
  }

  return dayjs(iso).fromNow();
}

/**
 * Returns the ISO string, or the current time as ISO if it is missing.
 * When a `fallback` date is provided, it takes precedence over the value.
 *
 * @example
 * valueOrNow("2025-01-15T10:00:00.000Z")                       // "2025-01-15T10:00:00.000Z"
 * valueOrNow("")                                               // "<current ISO>"
 * valueOrNow(null)                                             // "<current ISO>"
 * valueOrNow(undefined)                                        // "<current ISO>"
 * valueOrNow(null, new Date("2025-01-15"))                     // "2025-01-15T00:00:00.000Z"
 * valueOrNow("2025-01-15T10:00:00.000Z", new Date("2026-...")) // "2026-..." (fallback wins)
 */
export function valueOrNow(value: string | null | undefined, fallback?: Date): string {
  if (value === null || value === undefined || value === "") {
    return new Date().toISOString();
  }

  if (fallback !== undefined) {
    return fallback.toISOString();
  }

  return value;
}
