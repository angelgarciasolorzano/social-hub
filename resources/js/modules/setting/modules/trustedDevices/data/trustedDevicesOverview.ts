import { Calendar, Plus, Trash2, Users } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import {
  type DeviceActionGroup,
  twoFactorDeviceActionKey,
  type TwoFactorDeviceActionKey,
  twoFactorDeviceActions,
} from "@/modules/setting/modules/twoFactor/data/twoFactorEnable";

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

export const trustedDeviceRowActions: DeviceActionGroup[] = twoFactorDeviceActions;

export type TrustedDeviceRowActionKey = TwoFactorDeviceActionKey;

export { twoFactorDeviceActionKey as trustedDeviceRowActionKey };

export const trustedDeviceSectionActionKey = {
  addDevice: "add-device",
  deviceAlreadyRegistered: "device-already-registered",
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
