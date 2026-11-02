import {
  type DateTimeContext,
  formatActivationDate,
  formatActivationTime,
  formatFullDate,
  formatLongDate,
  formatShortDate,
  formatTimeUntil,
  fromNow,
} from "../lib/dateTime";
import { useTranslation } from "./useTranslation";

type DateFormatter = (iso: string | null) => string;

interface UseDateTimeReturn {
  formatActivationDate: DateFormatter;
  formatActivationTime: DateFormatter;
  formatFullDate: DateFormatter;
  formatLongDate: DateFormatter;
  formatShortDate: DateFormatter;
  formatTimeUntil: DateFormatter;
  fromNow: DateFormatter;
}

/**
 * The date helpers of `shared/lib/dateTime.ts` bound to the language the server applied and to the
 * `dates` catalog every page receives, so components format dates without passing a context.
 *
 * @returns Each formatter of `dateTime.ts`, taking only the ISO date.
 */
export function useDateTime(): UseDateTimeReturn {
  const { locale, t } = useTranslation();
  const context: DateTimeContext = { locale, t };

  return {
    formatActivationDate: (iso) => formatActivationDate(iso, context),
    formatActivationTime: (iso) => formatActivationTime(iso, context),
    formatFullDate: (iso) => formatFullDate(iso, context),
    formatLongDate: (iso) => formatLongDate(iso, context),
    formatShortDate: (iso) => formatShortDate(iso, context),
    formatTimeUntil: (iso) => formatTimeUntil(iso, context),
    fromNow: (iso) => fromNow(iso, context),
  };
}
