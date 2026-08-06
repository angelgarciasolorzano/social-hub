import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";
import { ChevronRight } from "lucide-react";

import { type HoverBorderColor, hoverBorderColors } from "@/shared/lib/styling/hoverBorderColors";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling/iconColorVariants";
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
  const hasAction = !!onOptionClick;

  return (
    <div className="flex flex-col gap-5">
      <h3 className="text-lg font-semibold">{title}</h3>

      <div className="flex items-stretch gap-4">
        {options.map((option) => {
          const Icon = option.icon;
          const colors = iconColorVariants[option.iconColor];

          return (
            <div
              key={option.key}
              onClick={() => {
                if (hasAction) onOptionClick(option.key);
              }}
              className={cn(
                "group flex flex-1 gap-4 rounded-xl border p-4 shadow-sm transition-all",
                hasAction && "cursor-pointer hover:shadow-lg",
                hasAction && hoverBorderColors[option.iconColor as HoverBorderColor],
              )}
            >
              <div
                className={cn(
                  "flex h-12 w-12 shrink-0 rounded-md p-2 transition-transform",
                  colors.iconBgClass,
                  hasAction && "group-hover:scale-110",
                )}
              >
                <Icon className={cn("h-8 w-8", colors.iconFgClass)} />
              </div>

              <div className="flex flex-1 flex-col gap-0.5">
                <h4 className="text-sm font-medium">{option.title}</h4>

                <p className="text-sm text-muted-foreground">{option.description}</p>
              </div>

              {hasAction && (
                <div className="flex items-center">
                  <ChevronRight
                    className={cn(
                      "h-5 w-5 transition-all group-hover:translate-x-1",
                      colors.iconFgClass,
                    )}
                  />
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
