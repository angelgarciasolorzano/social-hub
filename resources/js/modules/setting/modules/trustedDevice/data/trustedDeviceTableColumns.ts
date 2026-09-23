export const trustedDeviceTableColumnIds = {
  device: "device",
  lastAccess: "lastAccess",
  browserAndOs: "browserAndOs",
  status: "status",
  actions: "actions",
} as const;

export const trustedDeviceColumnVisibilityOptions = [
  { id: trustedDeviceTableColumnIds.lastAccess, label: "Último acceso" },
  { id: trustedDeviceTableColumnIds.browserAndOs, label: "Navegador / SO" },
  { id: trustedDeviceTableColumnIds.status, label: "Estado" },
] as const;
