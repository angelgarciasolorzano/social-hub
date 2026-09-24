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

export const trustedDeviceDefaultColumnVisibility: ColumnVisibilityState = {
  [trustedDeviceTableColumnIds.ip]: false,
};

export const trustedDeviceColumnVisibilityOptions = [
  { id: trustedDeviceTableColumnIds.lastAccess, label: "Último acceso" },
  { id: trustedDeviceTableColumnIds.browserAndOs, label: "Navegador / SO" },
  { id: trustedDeviceTableColumnIds.ip, label: "IP" },
  { id: trustedDeviceTableColumnIds.expiration, label: "Expira" },
  { id: trustedDeviceTableColumnIds.status, label: "Estado" },
] as const;
