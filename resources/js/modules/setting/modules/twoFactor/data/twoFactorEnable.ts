import {
  ChartNoAxesGantt,
  CircleX,
  LockKeyhole,
  MonitorSmartphone,
  RefreshCw,
  Repeat,
  RotateCcw,
  ShieldOff,
  TableOfContents,
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
    iconColor: "violet",
  },
  {
    key: twoFactorSecurityOptionsKey.regenerateCodes,
    title: "Regenerar códigos",
    description: "Genera nuevos códigos de respaldo. Los actuales dejarán de funcionar.",
    icon: Repeat,
    iconColor: "violet",
  },
  {
    key: twoFactorSecurityOptionsKey.disable2FA,
    title: "Desactivar 2FA",
    description: "Desactiva la autenticación de dos factores en tu cuenta.",
    icon: ShieldOff,
    iconColor: "red",
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

export const twoFactorManageActionKey = {
  viewCodes: "view-codes",
  regenerateCodes: "regenerate-codes",
  viewDevices: "view-devices",
  disable2FA: "disable-2fa",
} as const;

export type TwoFactorManageActionKey =
  (typeof twoFactorManageActionKey)[keyof typeof twoFactorManageActionKey];

export type TwoFactorManageAction = Pick<
  OptionCardItem<TwoFactorManageActionKey>,
  "key" | "icon"
> & {
  label: string;
  className?: string;
  iconClassName?: string;
};

export interface ManageActionGroup {
  label?: string;
  actions: TwoFactorManageAction[];
}

export const twoFactorManageActions: ManageActionGroup[] = [
  {
    actions: [
      {
        key: twoFactorManageActionKey.viewCodes,
        icon: ChartNoAxesGantt,
        label: "Ver Códigos de respaldo",
      },
      {
        key: twoFactorManageActionKey.regenerateCodes,
        icon: RefreshCw,
        label: "Regenerar códigos",
      },
      {
        key: twoFactorManageActionKey.viewDevices,
        icon: MonitorSmartphone,
        label: "Dispositivos de confianza",
      },
    ],
  },
  {
    actions: [
      {
        key: twoFactorManageActionKey.disable2FA,
        icon: CircleX,
        label: "Desactivar 2FA",
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];
