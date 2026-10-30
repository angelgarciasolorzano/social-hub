import type { TrustedDevicePerPage } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";

export const defaultTrustedDeviceActivityFilters = {
  action: null,
  sinceDays: null,
  search: "",
  perPage: 5,
} as const satisfies {
  action: TrustedDeviceActivityActionFilter[] | null;
  sinceDays: TrustedDeviceActivitySinceDaysFilter[] | null;
  search: string;
  perPage: TrustedDevicePerPage;
};

export const activityActionOptions = [
  { value: "created", label: "setting/trusted-device-dialogs.activity.actions.created" },
  { value: "renewed", label: "setting/trusted-device-dialogs.activity.actions.renewed" },
  { value: "renamed", label: "setting/trusted-device-dialogs.activity.actions.renamed" },
  { value: "revoked", label: "setting/trusted-device-dialogs.activity.actions.revoked" },
  { value: "revoked_all", label: "setting/trusted-device-dialogs.activity.actions.revoked_all" },
  { value: "reactivated", label: "setting/trusted-device-dialogs.activity.actions.reactivated" },
] as const;

export type TrustedDeviceActivityActionFilter = (typeof activityActionOptions)[number]["value"];

export const activitySinceDaysOptions = [
  { value: "7", label: "setting/trusted-device-dialogs.activity.since_days.last_7_days" },
  { value: "30", label: "setting/trusted-device-dialogs.activity.since_days.last_30_days" },
  { value: "90", label: "setting/trusted-device-dialogs.activity.since_days.last_90_days" },
  { value: "180", label: "setting/trusted-device-dialogs.activity.since_days.last_180_days" },
  { value: "365", label: "setting/trusted-device-dialogs.activity.since_days.all" },
] as const;

export type TrustedDeviceActivitySinceDaysFilter =
  (typeof activitySinceDaysOptions)[number]["value"];
