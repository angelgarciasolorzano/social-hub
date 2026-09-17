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
      "Manten solo dispositivos que realmente utilizas y reconoce. Elimina aquellos que ya no estes usando.",
    icon: Calendar,
    iconColor: "blue",
    title: "Revisa tus dispositivos periodicamente",
  },
  {
    description:
      "Marca como confiables unicamente dispositivos que esten bajo tu control. Evita computadoras publicas o equipos de uso compartido.",
    icon: Users,
    iconColor: "green",
    title: "No confies en dispositivos compartidos",
  },
  {
    description:
      "Si ves un dispositivo que te resulte extraño, revoca su acceso de inmediato. Asi evitaras que personas no autorizadas accedan a tu cuenta.",
    icon: Trash2,
    iconColor: "orange",
    title: "Revoca accesos que no reconzcas",
  },
  {
    description:
      "Reduce el riesgo de vulnerabilidades conocidas manteniendo tu navegador y sistema operativo al dia. Las actualizaciones suelen incluir parches de seguridad importantes para proteger tu cuenta.",
    icon: RefreshCw,
    iconColor: "purple",
    title: "Mantén tu navegador y sistema actualizados",
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

export const trustedDevicePreviewCopy = {
  body: "Revisa regularmente los dispositivos con acceso a tu cuenta y elimina aquellos que ya no utilices para evitar accesos no autorizados.",
  title: "Mantén el control de tus dispositivos",
} as const;

export const trustedDevicePreviewAlert = {
  body: "Cada dispositivo que revisas fortalece la seguridad de tu cuenta.",
  title: "Pequeñas acciones, gran diferencia",
} as const;
