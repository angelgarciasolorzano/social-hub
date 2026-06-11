import type { LucideIcon } from "lucide-react";
import { Monitor, Moon, Sun } from "lucide-react";

import type { Appearance as AppearanceType } from "@/shared/hooks/useAppearance";

interface AppearanceItem {
  icon: LucideIcon;
  label: string;
  value: AppearanceType;
}

export const appearanceItems: AppearanceItem[] = [
  {
    icon: Sun,
    label: "Light",
    value: "light",
  },
  {
    icon: Moon,
    label: "Dark",
    value: "dark",
  },
  {
    icon: Monitor,
    label: "System",
    value: "system",
  },
];
