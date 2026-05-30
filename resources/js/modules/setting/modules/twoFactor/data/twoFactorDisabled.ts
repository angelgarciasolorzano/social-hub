import type { ComponentType, SVGProps } from "react";

import {
  Authy,
  Google,
  Microsoft,
  OnePasswordDark,
  OnePasswordLight,
} from "@ridemountainpig/svgl-react";
import { KeyRound, LockKeyhole, MonitorSmartphone, ShieldCheck, Star } from "lucide-react";

import type { OptionCardItem } from "../components/OptionCard";
import type { Step } from "../components/Timeline";

export const twoFactorBenefits: OptionCardItem[] = [
  {
    key: "security",
    title: "Mas seguridad",
    description: "Protege tu cuenta contra accesos no autorizados.",
    icon: LockKeyhole,
    iconBgColor: "bg-green-100/50 dark:bg-green-900/20",
    iconColor: "text-green-700 dark:text-green-500",
  },
  {
    key: "verification",
    title: "Verificación adicional",
    description: "Requiere un código único además de tu contraseña.",
    icon: ShieldCheck,
    iconBgColor: "bg-blue-100/50 dark:bg-blue-900/20",
    iconColor: "text-blue-700 dark:text-blue-500",
  },
  {
    key: "standard",
    title: "Estandar confiable",
    description: "Utilizamos TOTP, un estandar ampliamente reconocido y seguro.",
    icon: Star,
    iconBgColor: "bg-purple-100/50 dark:bg-purple-900/20",
    iconColor: "text-purple-700 dark:text-purple-500",
  },
];

type TwoFactorImportantDetail = OptionCardItem;

export const twoFactorImportantDetails: TwoFactorImportantDetail[] = [
  {
    key: "standard",
    title: "Estandar confiable",
    description:
      "Guarda los codigos de respaldos que te proporcionaremos. Te permitiran acceder a tu cuenta si pierdes el telefono.",
    icon: KeyRound,
    iconBgColor: "bg-green-100/50 dark:bg-green-900/20",
    iconColor: "text-green-700 dark:text-green-500",
  },
  {
    key: "backup-codes",
    title: "Códigos de respaldo",
    description:
      "Cada código de recuperación se puede usar una vez para acceder a tu cuenta y se eliminará después de su uso. Si necesitas más, genera nuevos códigos después de activar 2FA.",
    icon: MonitorSmartphone,
    iconBgColor: "bg-yellow-100/50 dark:bg-yellow-900/20",
    iconColor: "text-yellow-500 dark:text-yellow-400",
  },
  {
    key: "devices-trusted",
    title: "Dispositivos de confianza",
    description:
      "Marca tus dispositivos como confiables para no tener que ingresar un código de verificación cada vez que inicies sesión desde ellos.",
    icon: ShieldCheck,
    iconBgColor: "bg-blue-100/50 dark:bg-blue-900/20",
    iconColor: "text-blue-700 dark:text-blue-500",
  },
  {
    key: "support",
    title: "¿Problemas?",
    description:
      "Si no puedes acceder a tu cuenta, usa un codigo de respaldo o contacta al soporte para obtener ayuda.",
    icon: LockKeyhole,
    iconBgColor: "bg-purple-100/50 dark:bg-purple-900/20",
    iconColor: "text-purple-700 dark:text-purple-500",
  },
];

type TwoFactorOperationStep = Step;

export const twoFactorOperationSteps: TwoFactorOperationStep[] = [
  {
    number: 1,
    title: "Escanea el código QR",
    description:
      "Abre tu aplicación autenticadora (Google Authenticator, Authy, 1Password, etc.) y escanea el código.",
  },
  {
    number: 2,
    title: "Obtén tu código",
    description: "La aplicación generará códigos de 6 dígitos que cambian cada 30 segundos.",
  },
  {
    number: 3,
    title: "Ingresa el código",
    description: "Ingresa el código de 6 dígitos para verificar y activar 2FA en tu cuenta.",
  },
];

type TwoFactorRequirement = Pick<OptionCardItem, "description" | "key">;

export const twoFactorRequirements: TwoFactorRequirement[] = [
  {
    key: "totp-app",
    description: "Una aplicación autenticadora compatible con TOTP",
  },
  {
    key: "mobile-access",
    description: "Acceso a tu teléfono móvil",
  },
  {
    key: "internet-connection",
    description: "Conexión a internet (para la configuracion inicial)",
  },
];

interface TwoFactorRecommendedApp {
  key: string;
  name: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  iconDark?: ComponentType<SVGProps<SVGSVGElement>>;
}

export const twoFactorRecommendedApps: TwoFactorRecommendedApp[] = [
  {
    key: "google-authenticator",
    name: "Google Authenticator",
    icon: Google,
  },
  {
    key: "authy",
    name: "Authy",
    icon: Authy,
  },
  {
    key: "one-password",
    name: "1Password",
    icon: OnePasswordLight,
    iconDark: OnePasswordDark,
  },
  {
    key: "microsoft-authenticator",
    name: "Microsoft Authenticator",
    icon: Microsoft,
  },
];
