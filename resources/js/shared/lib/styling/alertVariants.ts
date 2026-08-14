export const alertVariants = {
  info: "border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-500/10 dark:text-blue-500",
  success:
    "border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-500/10 dark:text-green-500",
  warning:
    "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-500/10 dark:text-amber-500",
  destructive:
    "border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-500/10 dark:text-red-500",
  preview:
    "border-purple-200 bg-purple-50 text-purple-900 dark:border-purple-900 dark:bg-purple-500/10 dark:text-purple-500",
} as const;

export type AlertVariant = keyof typeof alertVariants;
