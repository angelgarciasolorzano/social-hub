import type { ColumnVisibilityState } from "@tanstack/react-table";

export const trustedDeviceTableColumnIds = {
  device: "device",
  lastAccess: "lastAccess",
  browserAndOs: "browserAndOs",
  ip: "ip",
  expiration: "expiration",
  status: "status",
  actions: "actions",
} as const;

export const trustedDeviceTableColumnSizes = {
  device: 180,
  lastAccess: 140,
  browserAndOs: 165,
  ip: 150,
  expiration: 140,
  status: 100,
  actions: 115,
} as const;

export const trustedDeviceDefaultColumnVisibility: ColumnVisibilityState = {
  [trustedDeviceTableColumnIds.ip]: false,
};

export const trustedDeviceColumnVisibilityOptions = [
  {
    id: trustedDeviceTableColumnIds.lastAccess,
    label: "setting/trusted-devices.columns.last_access",
  },
  {
    id: trustedDeviceTableColumnIds.browserAndOs,
    label: "setting/trusted-devices.columns.browser_os",
  },
  { id: trustedDeviceTableColumnIds.ip, label: "setting/trusted-devices.columns.ip" },
  { id: trustedDeviceTableColumnIds.expiration, label: "setting/trusted-devices.columns.expires" },
  { id: trustedDeviceTableColumnIds.status, label: "setting/trusted-devices.columns.status" },
] as const;
