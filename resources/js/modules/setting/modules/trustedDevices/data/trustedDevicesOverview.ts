import { Calendar, Eye, Pencil, Plus, RotateCw, Trash2, Users } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import type { IconColorVariant } from "@/shared/lib/styling";

export interface TrustedDeviceRecommendation {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
}

export const trustedDeviceRecommendations: TrustedDeviceRecommendation[] = [
  {
    description: "Elimina los que ya no utilizas.",
    icon: Calendar,
    iconColor: "blue",
    title: "Revisa tus dispositivos periodicamente",
  },
  {
    description: "Marca solo equipos que esten bajo tu control.",
    icon: Users,
    iconColor: "green",
    title: "No confies en dispositivos compartidos",
  },
  {
    description: "Si ves algo extraño, elimina ese dispositivo.",
    icon: Trash2,
    iconColor: "orange",
    title: "Revoca accesos que no reconzcas",
  },
];

export const trustedDeviceRowActionKey = {
  viewDevice: "view-device",
  renameDevice: "rename-device",
  renewTrust: "renew-trust",
  revokeDevice: "revoke-device",
  reactivate: "reactivate",
  forceDestroy: "force-destroy",
} as const;

export type TrustedDeviceRowActionKey =
  (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];

export interface TrustedDeviceRowAction {
  key: TrustedDeviceRowActionKey;
  icon: LucideIcon;
  label: string;
  className?: string;
  iconClassName?: string;
  isEnabled: (device: TrustedDevice) => boolean;
}

export interface TrustedDeviceRowActionGroup {
  label?: string;
  actions: TrustedDeviceRowAction[];
}

const isRevoked = (device: TrustedDevice): boolean => device.deletedAt !== null;

export const trustedDeviceRowActions: TrustedDeviceRowActionGroup[] = [
  {
    label: "Acciones del dispositivo",
    actions: [
      {
        key: trustedDeviceRowActionKey.viewDevice,
        icon: Eye,
        label: "Ver dispositivo",
        isEnabled: () => true,
      },
      {
        key: trustedDeviceRowActionKey.renameDevice,
        icon: Pencil,
        label: "Renombrar dispositivo",
        isEnabled: () => true,
      },
      {
        key: trustedDeviceRowActionKey.renewTrust,
        icon: RotateCw,
        label: "Renovar confianza",
        isEnabled: (device) => !isRevoked(device),
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceRowActionKey.reactivate,
        icon: RotateCw,
        label: "Reactivar",
        isEnabled: isRevoked,
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceRowActionKey.revokeDevice,
        icon: Trash2,
        label: "Revocar dispositivo",
        isEnabled: (device) => !isRevoked(device),
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
      {
        key: trustedDeviceRowActionKey.forceDestroy,
        icon: Trash2,
        label: "Eliminar definitivamente",
        isEnabled: (device) => isRevoked(device),
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];

export const trustedDeviceSectionActionKey = {
  addDevice: "add-device",
  deviceAlreadyRegistered: "device-already-registered",
  deviceExpired: "device-expired",
  deviceRevoked: "device-revoked",
  revokeAll: "revoke-all",
} as const;

export type TrustedDeviceSectionActionKey =
  (typeof trustedDeviceSectionActionKey)[keyof typeof trustedDeviceSectionActionKey];

interface TrustedDeviceTitleAction {
  key: TrustedDeviceSectionActionKey;
  label: string;
  icon: LucideIcon;
  iconClassName?: string;
  className?: string;
}

export interface TrustedDeviceTitleActionGroup {
  label?: string;
  actions: TrustedDeviceTitleAction[];
}

export const trustedDeviceTitleActions: TrustedDeviceTitleActionGroup[] = [
  {
    actions: [
      {
        key: trustedDeviceSectionActionKey.addDevice,
        icon: Plus,
        label: "Agregar dispositivo",
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceSectionActionKey.revokeAll,
        icon: Trash2,
        label: "Revocar todos",
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];
