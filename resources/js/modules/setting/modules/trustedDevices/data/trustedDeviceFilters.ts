export const statusOptions = [
  { value: "active", label: "Activo" },
  { value: "inactive", label: "Expirado" },
] as const;

export type TrustedDeviceStatusFilter = (typeof statusOptions)[number]["value"];

export const browserOptions = [
  { value: "chrome", label: "Chrome" },
  { value: "firefox", label: "Firefox" },
  { value: "safari", label: "Safari" },
  { value: "edge", label: "Edge" },
  { value: "otro", label: "Otro" },
] as const;

export type TrustedDeviceBrowserFilter = (typeof browserOptions)[number]["value"];

export const trustedDevicePerPageOptions = [5, 10, 15, 25, 50] as const;

export type TrustedDevicePerPage = (typeof trustedDevicePerPageOptions)[number];

export const deviceTypeOptions = [
  { value: "desktop / laptop", label: "Escritorio / Portátil" },
  { value: "mobile / tablet", label: "Móvil / Tablet" },
] as const;

export const lastAccessOptions = [
  { value: "any", label: "Cualquier momento" },
  { value: "24h", label: "Últimas 24 horas" },
  { value: "7d", label: "Últimos 7 días" },
  { value: "30d", label: "Últimos 30 días" },
] as const;
