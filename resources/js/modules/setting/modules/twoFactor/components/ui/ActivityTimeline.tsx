import type { JSX, ReactNode } from "react";

import type { LucideIcon } from "lucide-react";

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

interface TimeLineVariantProperty {
  bg: string;
  text: string;
  line: string;
}

const variantStyles: Record<TimelineVariant, TimeLineVariantProperty> = {
  violet: {
    bg: "bg-violet-100/50 dark:bg-violet-900/20",
    text: "text-violet-700 dark:text-violet-500",
    line: "bg-violet-500 dark:bg-violet-500/70",
  },
  blue: {
    bg: "bg-blue-100/50 dark:bg-blue-900/20",
    text: "text-blue-700 dark:text-blue-500",
    line: "bg-blue-500 dark:bg-blue-800/50",
  },
  green: {
    bg: "bg-green-100/50 dark:bg-green-900/20",
    text: "text-green-700 dark:text-green-500",
    line: "bg-green-500 dark:bg-green-800/50",
  },
  red: {
    bg: "bg-red-100/50 dark:bg-red-900/20",
    text: "text-red-700 dark:text-red-500",
    line: "bg-red-500 dark:bg-red-800/50",
  },
  amber: {
    bg: "bg-amber-100/50 dark:bg-amber-900/20",
    text: "text-amber-700 dark:text-amber-500",
    line: "bg-amber-500 dark:bg-amber-800/50",
  },
};

function ActivityTimeline({ steps, variant = "violet" }: ActivityTimelineProps): JSX.Element {
  const styles = variantStyles[variant];

  return (
    <div>
      {steps.map((step, index) => {
        const Icon = step.icon;
        const isLast = index === steps.length - 1;

        return (
          <div className="flex gap-4" key={index}>
            <div className="flex flex-col items-center">
              <div
                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${styles.bg}`}
              >
                <Icon className={`h-5 w-5 ${styles.text}`} />
              </div>

              {!isLast && <div className={`w-[1.5px] flex-1 ${styles.line}`} />}
            </div>

            <div className="flex flex-col gap-1 pb-6">
              <h3 className="font-semibold">{step.title}</h3>

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
