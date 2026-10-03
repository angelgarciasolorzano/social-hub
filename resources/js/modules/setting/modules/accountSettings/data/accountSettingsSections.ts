import type { LucideIcon } from "lucide-react";
import { Bell, EyeOff, FileText, LockKeyhole, Mail, Megaphone, Settings2 } from "lucide-react";

export interface AccountPrivacyPrinciple {
  description: string;
  icon: LucideIcon;
  title: string;
}

export interface AccountCommunicationChannel {
  description: string;
  enabled: boolean;
  icon: LucideIcon;
  id: "email" | "system" | "news";
  title: string;
}

export const privacyPrinciples: readonly AccountPrivacyPrinciple[] = [
  {
    description: "Usamos cifrado para mantener tu información segura.",
    icon: LockKeyhole,
    title: "Datos protegidos",
  },
  {
    description: "Tus datos son visibles únicamente para ti.",
    icon: EyeOff,
    title: "Solo tú tienes acceso",
  },
  {
    description: "Puedes actualizar o eliminar tu información cuando quieras.",
    icon: Settings2,
    title: "Tú decides",
  },
  {
    description: "Te explicamos cómo usamos tus datos.",
    icon: FileText,
    title: "Transparencia",
  },
];

export const communicationChannels: readonly AccountCommunicationChannel[] = [
  {
    description: "Recibe actualizaciones importantes y noticias.",
    enabled: true,
    icon: Mail,
    id: "email",
    title: "Correos electrónicos",
  },
  {
    description: "Alertas y mensajes dentro de la plataforma.",
    enabled: true,
    icon: Bell,
    id: "system",
    title: "Notificaciones del sistema",
  },
  {
    description: "Recibe tips, mejoras y novedades de productos.",
    enabled: false,
    icon: Megaphone,
    id: "news",
    title: "Novedades y consejos",
  },
];
