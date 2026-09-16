import type { JSX, ReactNode } from "react";

import type { LucideIcon } from "lucide-react";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

export type TimelineVariant = "violet" | "blue" | "green" | "red" | "amber";

export interface ActivityStep {
  icon: LucideIcon;
  title: string;
  meta?: ReactNode;
}

interface ActivityTimelineProps {
  steps: ActivityStep[];
  variant?: TimelineVariant;
}

const timelineLineColors: Record<TimelineVariant, string> = {
  violet: "bg-violet-500 dark:bg-violet-500/70",
  blue: "bg-blue-500 dark:bg-blue-800/50",
  green: "bg-green-500 dark:bg-green-800/50",
  red: "bg-red-500 dark:bg-red-800/50",
  amber: "bg-amber-500 dark:bg-amber-800/50",
};

const variantIconColor: Record<TimelineVariant, IconColorVariant> = {
  violet: "violet",
  blue: "blue",
  green: "green",
  red: "red",
  amber: "amber",
};

function ActivityTimeline({ steps, variant = "violet" }: ActivityTimelineProps): JSX.Element {
  const colors = iconColorVariants[variantIconColor[variant]];
  const lineColor = timelineLineColors[variant];

  return (
    <div>
      {steps.map((step, index) => {
        const Icon = step.icon;
        const isLast = index === steps.length - 1;

        return (
          <div className="flex gap-4" key={index}>
            <div className="flex flex-col items-center">
              <div
                className={cn(
                  "flex h-10 w-10 shrink-0 items-center justify-center rounded-full",
                  colors.iconBgClass,
                )}
              >
                <Icon className={cn("h-5 w-5", colors.iconFgClass)} />
              </div>

              {!isLast && <div className={cn("w-[1.5px] flex-1", lineColor)} />}
            </div>

            <div className="flex flex-col gap-1 pb-6">
              <h3 className="text-sm font-medium">{step.title}</h3>

              {step.meta !== undefined && (
                <div className="text-sm text-muted-foreground">{step.meta}</div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default ActivityTimeline;
