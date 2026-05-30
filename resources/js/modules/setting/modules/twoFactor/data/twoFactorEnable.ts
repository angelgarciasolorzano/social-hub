import {
  LockKeyhole,
  MonitorSmartphone,
  Repeat,
  RotateCcw,
  ShieldOff,
  TableOfContents,
} from "lucide-react";

import type { OptionCardItem } from "../components/OptionCard";

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
