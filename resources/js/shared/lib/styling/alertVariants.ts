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
  yellow:
    "border-yellow-200 bg-yellow-50 text-yellow-900 dark:border-yellow-900 dark:bg-yellow-500/10 dark:text-yellow-500",
  orange:
    "border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-900 dark:bg-orange-500/10 dark:text-orange-500",
  violet:
    "border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-900 dark:bg-violet-500/10 dark:text-violet-500",
  cyan: "border-cyan-200 bg-cyan-50 text-cyan-900 dark:border-cyan-900 dark:bg-cyan-500/10 dark:text-cyan-500",
  gray: "border-gray-200 bg-gray-50 text-gray-900 dark:border-gray-900 dark:bg-gray-500/10 dark:text-gray-500",
} as const;

export type AlertVariant = keyof typeof alertVariants;
