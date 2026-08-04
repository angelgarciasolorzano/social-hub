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
