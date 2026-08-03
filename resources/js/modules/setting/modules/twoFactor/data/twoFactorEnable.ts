import {
  Eye,
  LockKeyhole,
  MonitorSmartphone,
  Pencil,
  Repeat,
  RotateCcw,
  RotateCw,
  ShieldOff,
  TableOfContents,
  Trash2,
} from "lucide-react";

import type { OptionCardItem } from "../components/ui/OptionCard";

export const twoFactorSecurityOptionsKey = {
  backupCodes: "backup-codes",
  regenerateCodes: "regenerate-codes",
  disable2FA: "disable-2fa",
} as const;

export type TwoFactorSecurityOptionKey =
  (typeof twoFactorSecurityOptionsKey)[keyof typeof twoFactorSecurityOptionsKey];

export const twoFactorSecurityOptions: OptionCardItem<TwoFactorSecurityOptionKey>[] = [
  {
    key: twoFactorSecurityOptionsKey.backupCodes,
    title: "Códigos de respaldo",
    description: "Usa estos códigos si no tienes acceso a tu autenticador.",
    icon: TableOfContents,
    iconBgColor: "bg-violet-100/50 dark:bg-violet-900/20",
    iconColor: "text-violet-700 dark:text-violet-500",
  },
  {
    key: twoFactorSecurityOptionsKey.regenerateCodes,
    title: "Regenerar códigos",
    description: "Genera nuevos códigos de respaldo. Los actuales dejarán de funcionar.",
    icon: Repeat,
    iconBgColor: "bg-violet-100/50 dark:bg-violet-900/20",
    iconColor: "text-violet-700 dark:text-violet-500",
  },
  {
    key: twoFactorSecurityOptionsKey.disable2FA,
    title: "Desactivar 2FA",
    description: "Desactiva la autenticación de dos factores en tu cuenta.",
    icon: ShieldOff,
    iconBgColor: "bg-red-100/50 dark:bg-red-900/20",
    iconColor: "text-red-700 dark:text-red-500",
  },
];

type TwoFactorSafetyTip = Pick<OptionCardItem, "key" | "title" | "description" | "icon">;

export const twoFactorSafetyTips: TwoFactorSafetyTip[] = [
  {
    key: "save-backup-codes",
    icon: LockKeyhole,
    title: "Guarda tus códigos",
    description: "Almacena tus códigos de respaldo en un lugar seguro y accesible para ti.",
  },
  {
    key: "use-devices-trust",
    icon: MonitorSmartphone,
    title: "Usa dispositivos de confianza",
    description: "Marca dispositivos como confiables para evitar verificaciones frecuentes.",
  },
  {
    key: "keep-your-app-updated",
    icon: RotateCcw,
    title: "Manten tu aplicacion actualizada",
    description:
      "Asegúrate de tener la ultima version de tu aplicacion autenticadora para garantizar la  mejor seguridad.",
  },
];

export const twoFactorDeviceSectionActionKey = {
  addDevice: "add-device",
  deviceAlreadyRegistered: "device-already-registered",
  revokeAllDevices: "revoke-all-devices",
} as const;

export type TwoFactorDeviceSectionActionKey =
  (typeof twoFactorDeviceSectionActionKey)[keyof typeof twoFactorDeviceSectionActionKey];

export const twoFactorDeviceActionKey = {
  viewDevice: "view-device",
  renameDevice: "rename-device",
  renewTrust: "renew-trust",
  revokeDevice: "revoke-device",
} as const;

export type TwoFactorDeviceActionKey =
  (typeof twoFactorDeviceActionKey)[keyof typeof twoFactorDeviceActionKey];

export type TwoFactorDeviceAction = Pick<
  OptionCardItem<TwoFactorDeviceActionKey>,
  "key" | "icon"
> & {
  label: string;
  className?: string;
  iconClassName?: string;
};

export interface DeviceActionGroup {
  label?: string;
  actions: TwoFactorDeviceAction[];
}

export const twoFactorDeviceActions: DeviceActionGroup[] = [
  {
    label: "Acciones del dispositivo",
    actions: [
      {
        key: twoFactorDeviceActionKey.viewDevice,
        icon: Eye,
        label: "Ver dispositivo",
      },
      {
        key: twoFactorDeviceActionKey.renameDevice,
        icon: Pencil,
        label: "Renombrar dispositivo",
      },
      {
        key: twoFactorDeviceActionKey.renewTrust,
        icon: RotateCw,
        label: "Renovar confianza",
      },
    ],
  },
  {
    actions: [
      {
        key: twoFactorDeviceActionKey.revokeDevice,
        icon: Trash2,
        label: "Revocar dispositivo",
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];
