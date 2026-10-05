import type { LucideIcon } from "lucide-react";
import { Bell, Eye, FileText, Mail, Megaphone, Settings2, UserRound } from "lucide-react";

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
    description: "Consulta y actualiza la información personal asociada a tu cuenta.",
    icon: UserRound,
    iconColor: "blue",
    title: "Datos de tu cuenta",
  },
  {
    description: "Consulta cómo se muestra la información de tu perfil público.",
    icon: Eye,
    iconColor: "violet",
    title: "Perfil público",
  },
  {
    description: "Puedes actualizar tus datos o eliminar tu cuenta desde las opciones disponibles.",
    icon: Settings2,
    iconColor: "amber",
    title: "Tú decides",
  },
  {
    description: "Revisa las acciones disponibles para administrar tu cuenta.",
    icon: FileText,
    iconColor: "green",
    title: "Opciones de cuenta",
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
