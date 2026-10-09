import { Calendar, Laptop, Monitor, RefreshCw, Smartphone, Trash2, Users } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type { IconColorVariant } from "@/shared/lib/styling";

export interface TrustedDeviceRecommendation {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
}

export const trustedDeviceRecommendations: TrustedDeviceRecommendation[] = [
  {
    description:
      "Mantén solo los dispositivos que realmente usas y reconoces. Elimina los que ya no utilices.",
    icon: Calendar,
    iconColor: "blue",
    title: "Revisa tus dispositivos periódicamente",
  },
  {
    description:
      "Marca como confiables solo equipos bajo tu control. Evita computadoras públicas o de uso compartido.",
    icon: Users,
    iconColor: "green",
    title: "No confíes en dispositivos compartidos",
  },
  {
    description:
      "Si ves un dispositivo extraño, revoca su acceso de inmediato para evitar que personas no autorizadas entren a tu cuenta.",
    icon: Trash2,
    iconColor: "orange",
    title: "Revoca accesos que no reconozcas",
  },
  {
    description:
      "Las actualizaciones incluyen parches de seguridad que reducen el riesgo de vulnerabilidades conocidas.",
    icon: RefreshCw,
    iconColor: "purple",
    title: "Mantén tu navegador y sistema al día",
  },
];

export interface TrustedDevicePreviewItem {
  icon: LucideIcon;
  iconVariant: IconColorVariant;
  name: string;
  subtitle: string;
}

export interface TrustedDevicePreviewBadge {
  label: string;
  variant: IconColorVariant;
}

export interface TrustedDevicePreviewRow {
  badge: TrustedDevicePreviewBadge;
  device: TrustedDevicePreviewItem;
}

export const trustedDevicePreviewRows: readonly TrustedDevicePreviewRow[] = [
  {
    badge: { label: "Confiable", variant: "green" },
    device: {
      icon: Laptop,
      iconVariant: "blue",
      name: "MacBook Pro",
      subtitle: "Escritorio",
    },
  },
  {
    badge: { label: "Confiable", variant: "green" },
    device: {
      icon: Smartphone,
      iconVariant: "green",
      name: "iPhone 14",
      subtitle: "Móvil",
    },
  },
  {
    badge: { label: "Revocar", variant: "red" },
    device: {
      icon: Monitor,
      iconVariant: "red",
      name: "Windows PC",
      subtitle: "Escritorio",
    },
  },
];

export const trustedDevicePreviewAlert = {
  body: "Cada dispositivo que revisas fortalece la seguridad de tu cuenta.",
  title: "Pequeñas acciones, gran diferencia",
} as const;
