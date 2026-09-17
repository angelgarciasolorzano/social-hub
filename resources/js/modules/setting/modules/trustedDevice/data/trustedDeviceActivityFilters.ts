export const activityActionOptions = [
  { value: "created", label: "Agregados" },
  { value: "renewed", label: "Renovados" },
  { value: "renamed", label: "Renombrados" },
  { value: "revoked", label: "Revocados" },
  { value: "revoked_all", label: "Todos revocados" },
  { value: "reactivated", label: "Reactivados" },
] as const;

export type TrustedDeviceActivityActionFilter = (typeof activityActionOptions)[number]["value"];

export const ALL_ACTIVITY_ACTIONS = null;

export const activitySinceDaysOptions = [
  { value: "7", label: "Últimos 7 días" },
  { value: "30", label: "Últimos 30 días" },
  { value: "90", label: "Últimos 90 días" },
  { value: "180", label: "Últimos 180 días" },
  { value: "365", label: "Todos" },
] as const;

export type TrustedDeviceActivitySinceDaysFilter =
  (typeof activitySinceDaysOptions)[number]["value"];

export const ALL_ACTIVITY_SINCE_DAYS = null;

export const defaultActivityFilters = {
  action: ALL_ACTIVITY_ACTIONS,
  sinceDays: ALL_ACTIVITY_SINCE_DAYS,
} as const satisfies {
  action: TrustedDeviceActivityActionFilter[] | null;
  sinceDays: TrustedDeviceActivitySinceDaysFilter[] | null;
};
