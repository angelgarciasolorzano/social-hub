import { Calendar, Trash2, Users } from "lucide-react";
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
