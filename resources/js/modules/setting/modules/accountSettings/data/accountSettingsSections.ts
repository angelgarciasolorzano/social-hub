import type { LucideIcon } from "lucide-react";
import { Bell, EyeOff, FileText, LockKeyhole, Mail, Megaphone, Settings2 } from "lucide-react";

import type { IconColorVariant } from "@/shared/lib/styling";

export interface AccountPrivacyPrinciple {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
}

export interface AccountCommunicationChannel {
  description: string;
  enabled: boolean;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  id: "email" | "system" | "news";
  title: string;
}

export const privacyPrinciples: readonly AccountPrivacyPrinciple[] = [
  {
    description: "Usamos cifrado para mantener tu información segura.",
    icon: LockKeyhole,
    iconColor: "blue",
    title: "Datos protegidos",
  },
  {
    description: "Tus datos son visibles únicamente para ti.",
    icon: EyeOff,
    iconColor: "violet",
    title: "Solo tú tienes acceso",
  },
  {
    description: "Puedes actualizar o eliminar tu información cuando quieras.",
    icon: Settings2,
    iconColor: "amber",
    title: "Tú decides",
  },
  {
    description: "Te explicamos cómo usamos tus datos.",
    icon: FileText,
    iconColor: "green",
    title: "Transparencia",
  },
];

export const communicationChannels: readonly AccountCommunicationChannel[] = [
  {
    description: "Recibe actualizaciones importantes y noticias.",
    enabled: true,
    icon: Mail,
    iconColor: "blue",
    id: "email",
    title: "Correos electrónicos",
  },
  {
    description: "Alertas y mensajes dentro de la plataforma.",
    enabled: true,
    icon: Bell,
    iconColor: "green",
    id: "system",
    title: "Notificaciones del sistema",
  },
  {
    description: "Recibe tips, mejoras y novedades de productos.",
    enabled: false,
    icon: Megaphone,
    iconColor: "violet",
    id: "news",
    title: "Novedades y consejos",
  },
];
