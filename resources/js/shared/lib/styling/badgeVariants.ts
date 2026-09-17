export const badgeVariants = {
  success: "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300",
  warning: "bg-yellow-50 text-yellow-700 dark:bg-yellow-950/50 dark:text-yellow-300",
  preview: "bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300",
  blue: "bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
  amber: "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
  orange: "bg-orange-50 text-orange-700 dark:bg-orange-950 dark:text-orange-300",
  red: "bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300",
  violet: "bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300",
  cyan: "bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300",
  gray: "bg-gray-50 text-gray-700 dark:bg-gray-950 dark:text-gray-300",
} as const;

export type BadgeVariant = keyof typeof badgeVariants;
