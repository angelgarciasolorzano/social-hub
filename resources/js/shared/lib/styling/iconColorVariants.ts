export const iconColorVariants = {
  blue: {
    iconBgClass: "bg-blue-100/50 dark:bg-blue-900/20",
    iconFgClass: "text-blue-700 dark:text-blue-500",
  },
  green: {
    iconBgClass: "bg-green-100/50 dark:bg-green-900/20",
    iconFgClass: "text-green-700 dark:text-green-500",
  },
  yellow: {
    iconBgClass: "bg-yellow-100/50 dark:bg-yellow-900/20",
    iconFgClass: "text-yellow-700 dark:text-yellow-500",
  },
  amber: {
    iconBgClass: "bg-amber-100/50 dark:bg-amber-900/20",
    iconFgClass: "text-amber-700 dark:text-amber-500",
  },
  orange: {
    iconBgClass: "bg-orange-100/50 dark:bg-orange-900/20",
    iconFgClass: "text-orange-700 dark:text-orange-500",
  },
  red: {
    iconBgClass: "bg-red-100/50 dark:bg-red-900/20",
    iconFgClass: "text-red-700 dark:text-red-500",
  },
  violet: {
    iconBgClass: "bg-violet-100/50 dark:bg-violet-900/20",
    iconFgClass: "text-violet-700 dark:text-violet-500",
  },
  purple: {
    iconBgClass: "bg-purple-100/50 dark:bg-purple-900/20",
    iconFgClass: "text-purple-700 dark:text-purple-500",
  },
  cyan: {
    iconBgClass: "bg-cyan-100/50 dark:bg-cyan-900/20",
    iconFgClass: "text-cyan-700 dark:text-cyan-500",
  },
  gray: {
    iconBgClass: "bg-gray-100/50 dark:bg-gray-900/20",
    iconFgClass: "text-gray-700 dark:text-gray-500",
  },
} as const;

export type IconColorVariant = keyof typeof iconColorVariants;
