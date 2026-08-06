export const badgeVariants = {
  success: "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300",
  warning: "bg-yellow-50 text-yellow-700 dark:bg-yellow-950/50 dark:text-yellow-300",
  preview: "bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300",
} as const;

export type BadgeVariant = keyof typeof badgeVariants;
