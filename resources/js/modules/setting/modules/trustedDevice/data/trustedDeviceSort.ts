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
    label: "setting/trusted-devices.sort.most_recent.label",
    description: "setting/trusted-devices.sort.most_recent.description",
  },
  {
    value: trustedDeviceSortKey.oldest,
    label: "setting/trusted-devices.sort.oldest.label",
    description: "setting/trusted-devices.sort.oldest.description",
  },
  {
    value: trustedDeviceSortKey.nameAsc,
    label: "setting/trusted-devices.sort.name_asc.label",
    description: "setting/trusted-devices.sort.name_asc.description",
  },
  {
    value: trustedDeviceSortKey.nameDesc,
    label: "setting/trusted-devices.sort.name_desc.label",
    description: "setting/trusted-devices.sort.name_desc.description",
  },
  {
    value: trustedDeviceSortKey.expiringSoon,
    label: "setting/trusted-devices.sort.expiring_soon.label",
    description: "setting/trusted-devices.sort.expiring_soon.description",
  },
];

export const defaultTrustedDeviceSort: TrustedDeviceSortKey = trustedDeviceSortKey.mostRecent;
