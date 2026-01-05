import { LucideIcon, Monitor, Moon, Sun } from "lucide-react";

import { Appearance as AppearanceType } from "@/hooks/use-appearance";

interface AppearanceItem {
  label: string;
  value: AppearanceType;
  icon: LucideIcon;
}

export const appearanceItems: AppearanceItem[] = [
  {
    label: "Light",
    icon: Sun,
    value: "light",
  },
  {
    label: "Dark",
    icon: Moon,
    value: "dark",
  },
  {
    label: "System",
    icon: Monitor,
    value: "system",
  },
];
