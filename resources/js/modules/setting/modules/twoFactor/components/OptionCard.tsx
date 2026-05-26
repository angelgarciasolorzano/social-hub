import type { LucideIcon } from "lucide-react";
import { ChevronRight } from "lucide-react";

import { cn } from "@/shared/lib/utils";

import colorHoverMap from "../utils/colorHoverMap";

export interface Option {
  key: string;
  title: string;
  description: string;
  icon: LucideIcon;
  iconBgColor: string;
  iconColor: string;
}

interface OptionCardProps {
  options: Option[];
  title?: string;
  onOptionClick?: (optionKey: string) => void;
}

export function OptionCard({
  options,
  title = "Beneficios de activar 2FA",
  onOptionClick,
}: OptionCardProps) {
  const hasAction = !!onOptionClick;

  return (
    <div className="flex flex-col gap-5">
      <h3 className="text-lg font-semibold">{title}</h3>

      <div className="flex items-stretch gap-4">
        {options.map((option) => {
          const Icon = option.icon;

          const colorMatch = option.iconColor.match(/text-(\w+)-\d+/);
          const colorName = colorMatch?.[1] || "gray";

          return (
            <div
              key={option.key}
              onClick={() => hasAction && onOptionClick(option.key)}
              className={cn(
                "group relative flex flex-1 gap-4 rounded-md border p-4 shadow-sm transition-all",
                hasAction && "cursor-pointer hover:shadow-lg",
                hasAction && colorHoverMap[colorName],
              )}
            >
              <div
                className={cn(
                  "flex h-12 w-12 shrink-0 rounded-md p-2 transition-transform",
                  option.iconBgColor,
                  hasAction && "group-hover:scale-110",
                )}
              >
                <Icon className={cn("h-8 w-8", option.iconColor)} />
              </div>

              <div className="flex flex-1 flex-col justify-between space-y-0.5">
                <div className="space-y-0.5">
                  <h4 className="text-sm font-medium">{option.title}</h4>

                  <p className="text-sm text-muted-foreground">{option.description}</p>
                </div>
              </div>

              {hasAction && (
                <div className="flex items-center">
                  <ChevronRight
                    className={cn(
                      "h-5 w-5 transition-all group-hover:translate-x-1",
                      option.iconColor,
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
