import type { IconColorVariant } from "./iconColorVariants";

export const progressBarClassesByVariant = {
  amber:
    "bg-amber-200 dark:bg-amber-800 **:data-[slot=progress-indicator]:bg-amber-700 dark:**:data-[slot=progress-indicator]:bg-amber-400",
  blue: "bg-blue-200 dark:bg-blue-800 **:data-[slot=progress-indicator]:bg-blue-700 dark:**:data-[slot=progress-indicator]:bg-blue-400",
  cyan: "bg-cyan-200 dark:bg-cyan-800 **:data-[slot=progress-indicator]:bg-cyan-700 dark:**:data-[slot=progress-indicator]:bg-cyan-400",
  gray: "bg-gray-200 dark:bg-gray-800 **:data-[slot=progress-indicator]:bg-gray-700 dark:**:data-[slot=progress-indicator]:bg-gray-400",
  green:
    "bg-green-200 dark:bg-green-800 **:data-[slot=progress-indicator]:bg-green-700 dark:**:data-[slot=progress-indicator]:bg-green-400",
  orange:
    "bg-orange-200 dark:bg-orange-800 **:data-[slot=progress-indicator]:bg-orange-700 dark:**:data-[slot=progress-indicator]:bg-orange-400",
  purple:
    "bg-purple-200 dark:bg-purple-800 **:data-[slot=progress-indicator]:bg-purple-700 dark:**:data-[slot=progress-indicator]:bg-purple-400",
  red: "bg-red-200 dark:bg-red-800 **:data-[slot=progress-indicator]:bg-red-700 dark:**:data-[slot=progress-indicator]:bg-red-400",
  violet:
    "bg-violet-200 dark:bg-violet-800 **:data-[slot=progress-indicator]:bg-violet-700 dark:**:data-[slot=progress-indicator]:bg-violet-400",
  yellow:
    "bg-yellow-200 dark:bg-yellow-800 **:data-[slot=progress-indicator]:bg-yellow-700 dark:**:data-[slot=progress-indicator]:bg-yellow-400",
} as const satisfies Record<IconColorVariant, string>;
