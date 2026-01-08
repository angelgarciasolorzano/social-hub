import { HTMLAttributes } from "react";

import { cn } from "@/lib/utils";

import { useAppearance } from "@/hooks/use-appearance";

import { appearanceItems } from "../data/settingsAppearanceItems";

function Appearance({ className = "", ...props }: HTMLAttributes<HTMLDivElement>) {
  const { appearance, updateAppearance } = useAppearance();

  return (
    <div
      className={cn(
        "inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800",
        className,
      )}
      {...props}
    >
      {appearanceItems.map(({ icon: Icon, label, value }) => (
        <button
          className={cn(
            "flex items-center rounded-md px-3.5 py-1.5 transition-colors",
            appearance === value
              ? "bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100"
              : "text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60",
          )}
          onClick={() => updateAppearance(value)}
          key={value}
        >
          <Icon className="-ml-1 h-4 w-4" />
          <span className="ml-1.5 text-sm">{label}</span>
        </button>
      ))}
    </div>
  );
}

export default Appearance;
