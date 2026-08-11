import dayjs from "dayjs";

const LONG_DATE_FORMAT = "D [de] MMMM [del] YYYY, h:mm A";
const ACTIVATION_DATE_FORMAT = "D [de] MMMM [de] YYYY";
const ACTIVATION_TIME_FORMAT = "h:mm A";

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
 * Formats an ISO date string into the activation date format (no time, no "del").
 * Example: "15 de marzo de 2024". Returns "No disponible" if the date is null.
 */
export function formatActivationDate(iso: string | null): string {
  if (iso === null) {
    return "No disponible";
  }

  return dayjs(iso).format(ACTIVATION_DATE_FORMAT);
}

/**
 * Formats an ISO date string into a localized 12-hour time with a GMT offset suffix.
 * Example: "11:45 AM (GMT-6)" / "11:45 AM (GMT+5:30)". Returns "No disponible" if null.
 */
export function formatActivationTime(iso: string | null): string {
  if (iso === null) {
    return "No disponible";
  }

  const date = dayjs(iso);
  const offset = date.format("Z");
  const formattedOffset = offset.replace(/^([+-])0/, "$1").replace(":00", "");

  return `${date.format(ACTIVATION_TIME_FORMAT)} (GMT${formattedOffset})`;
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
