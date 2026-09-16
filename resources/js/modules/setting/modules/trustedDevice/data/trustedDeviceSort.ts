export const trustedDeviceSortKey = {
  mostRecent: "most-recent",
  oldest: "oldest",
  nameAsc: "name-asc",
  nameDesc: "name-desc",
  expiringSoon: "expiring-soon",
} as const;

export type TrustedDeviceSortKey = (typeof trustedDeviceSortKey)[keyof typeof trustedDeviceSortKey];

export interface TrustedDeviceSortOption {
  value: TrustedDeviceSortKey;
  label: string;
  description: string;
}

export const trustedDeviceSortOptions: TrustedDeviceSortOption[] = [
  {
    value: trustedDeviceSortKey.mostRecent,
    label: "Mas recientes",
    description: "Ultimo acceso (mas reciente primero)",
  },
  {
    value: trustedDeviceSortKey.oldest,
    label: "Mas antiguos",
    description: "Ultimo acceso (mas antiguo primero)",
  },
  {
    value: trustedDeviceSortKey.nameAsc,
    label: "Nombre (A - Z)",
    description: "Nombre del dispositivo (A a Z)",
  },
  {
    value: trustedDeviceSortKey.nameDesc,
    label: "Nombre (Z - A)",
    description: "Nombre del dispositivo (Z a A)",
  },
  {
    value: trustedDeviceSortKey.expiringSoon,
    label: "Proximos a expirar",
    description: "Dispositivos que expiran primero",
  },
];

export const defaultTrustedDeviceSort: TrustedDeviceSortKey = trustedDeviceSortKey.mostRecent;
