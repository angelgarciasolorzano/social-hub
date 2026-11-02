export const statusOptions = [
  { value: "active", label: "setting/trusted-devices.status.active" },
  { value: "inactive", label: "setting/trusted-devices.status.expired" },
  { value: "revoked", label: "setting/trusted-devices.status.revoked" },
] as const;

export type TrustedDeviceStatusFilter = (typeof statusOptions)[number]["value"];

export const trustedDeviceStatusFilterValue = {
  active: "active",
  inactive: "inactive",
  revoked: "revoked",
} as const satisfies Record<string, TrustedDeviceStatusFilter>;

export const browserOptions = [
  { value: "chrome", label: "Chrome" },
  { value: "firefox", label: "Firefox" },
  { value: "safari", label: "Safari" },
  { value: "edge", label: "Edge" },
  { value: "otro", label: "setting/trusted-devices.filters.browsers.other" },
] as const;

export type TrustedDeviceBrowserFilter = (typeof browserOptions)[number]["value"];

export const trustedDevicePerPageOptions = [5, 10, 15, 25, 50] as const;

export const defaultTrustedDevicePerPage = 15;

export type TrustedDevicePerPage = (typeof trustedDevicePerPageOptions)[number];

export const deviceTypeOptions = [
  { value: "desktop / laptop", label: "setting/trusted-devices.filters.device_types.desktop" },
  { value: "mobile / tablet", label: "setting/trusted-devices.filters.device_types.mobile" },
] as const;

export type TrustedDeviceDeviceTypeFilter = (typeof deviceTypeOptions)[number]["value"];

export const lastAccessOptions = [
  { value: "24h", label: "setting/trusted-devices.filters.last_access.last_24_hours" },
  { value: "7d", label: "setting/trusted-devices.filters.last_access.last_7_days" },
  { value: "30d", label: "setting/trusted-devices.filters.last_access.last_30_days" },
] as const;

export type TrustedDeviceLastAccessFilter = (typeof lastAccessOptions)[number]["value"];
