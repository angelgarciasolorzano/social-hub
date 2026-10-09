import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";
import { ChevronRight } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";

import {
  type HoverBorderVariant,
  hoverBorderVariants,
  type IconColorVariant,
  iconColorVariants,
} from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

export interface OptionCardItem<TKey extends string = string> {
  key: TKey;
  title: string;
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
}

interface OptionCardProps<TKey extends string = string> {
  options: OptionCardItem<TKey>[];
  title?: string;
  onOptionClick?: (optionKey: TKey) => void;
}

export function OptionCard<TKey extends string = string>({
  options,
  title = "Beneficios de activar 2FA",
  onOptionClick,
}: OptionCardProps<TKey>): JSX.Element {
  const hasAction = onOptionClick !== undefined;

  return (
    <div className="flex flex-col gap-5">
      <h2 className="text-lg font-semibold tracking-tight">{title}</h2>

      <ul className="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" role="list">
        {options.map((option) => {
          const Icon = option.icon;
          const colors = iconColorVariants[option.iconColor];
          const cardClassName = cn(
            "group flex w-full min-w-0 gap-4 rounded-xl border p-4 text-left shadow-xs transition-all",
            hasAction && "cursor-pointer hover:shadow-sm",
            hasAction && hoverBorderVariants[option.iconColor as HoverBorderVariant],
          );
          const cardContent = (
            <>
              <span
                aria-hidden="true"
                className={cn(
                  "flex h-12 w-12 shrink-0 rounded-md p-2 transition-transform",
                  colors.iconBgClass,
                  hasAction && "group-hover:scale-110",
                )}
              >
                <Icon className={cn("size-8", colors.iconFgClass)} />
              </span>

              <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="text-sm font-medium">{option.title}</span>

                <span className="text-xs text-muted-foreground">{option.description}</span>
              </span>

              {hasAction && (
                <span aria-hidden="true" className="flex items-center">
                  <ChevronRight
                    className={cn(
                      "size-5 transition-all group-hover:translate-x-1",
                      colors.iconFgClass,
                    )}
                  />
                </span>
              )}
            </>
          );

          return (
            <li className="flex min-w-0" key={option.key}>
              {hasAction ? (
                <Button
                  className={cn(
                    cardClassName,
                    "h-auto items-start justify-start font-normal whitespace-normal hover:bg-transparent hover:text-current",
                  )}
                  onClick={() => {
                    onOptionClick(option.key);
                  }}
                  type="button"
                  variant="ghost"
                >
                  {cardContent}
                </Button>
              ) : (
                <div className={cardClassName}>{cardContent}</div>
              )}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
