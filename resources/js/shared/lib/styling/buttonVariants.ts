export const buttonVariants = {
  destructive: "dark:bg-red-700 dark:text-white dark:hover:bg-red-800",
} as const;

export type ButtonVariant = keyof typeof buttonVariants;
