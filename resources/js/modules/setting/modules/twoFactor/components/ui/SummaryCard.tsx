import type { JSX, ReactNode } from "react";

import type { LucideIcon } from "lucide-react";

import { Card, CardContent, CardHeader } from "@/shared/components/shadcn/ui/card";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

type BadgeVariant =
  "default" | "destructive" | "link" | "secondary" | "outline" | "ghost" | null | undefined;

export type SumaryCardAction =
  | { type: "badge"; variant: BadgeVariant; label: string }
  | { type: "button"; label: string; onClick: () => void }
  | { type: "progress"; current: number; total: number }
  | { type: "chevron"; onClick: () => void }
  | { type: "none" };

export interface SumaryCardItem {
  key: string;
  title: string;
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  action?: SumaryCardAction;
}

interface SummaryCardProps {
  title: string;
  data: SumaryCardItem[];
  showLastSeparator?: boolean;
  renderAction?: (action: SumaryCardAction) => ReactNode;
}

function SummaryCard({
  title,
  showLastSeparator,
  data,
  renderAction,
}: SummaryCardProps): JSX.Element {
  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg leading-none font-semibold tracking-tight">{title}</h2>
      </CardHeader>
      <CardContent>
        <ul className="flex flex-col gap-4" role="list">
          {data.map((summary, index) => {
            const Icon = summary.icon;
            const colors = iconColorVariants[summary.iconColor];

            return (
              <li className="flex flex-col gap-4" key={summary.key}>
                <div
                  className={cn(
                    "flex items-center gap-4",
                    !summary.action && !renderAction && "p-2",
                  )}
                >
                  <div
                    aria-hidden="true"
                    className={cn("flex h-12 w-12 rounded-md p-2", colors.iconBgClass)}
                  >
                    <Icon className={cn("h-8 w-8", colors.iconFgClass)} />
                  </div>

                  <div className="flex w-full min-w-0 items-center justify-between gap-4">
                    <div className="min-w-0 space-y-1">
                      <h3 className="text-sm font-medium">{summary.title}</h3>

                      <p className="text-sm text-muted-foreground">{summary.description}</p>
                    </div>

                    {summary.action && renderAction && (
                      <div className="flex items-center">{renderAction(summary.action)}</div>
                    )}
                  </div>
                </div>

                {showLastSeparator && index < data.length - 1 && <Separator />}
              </li>
            );
          })}
        </ul>
      </CardContent>
    </Card>
  );
}

export default SummaryCard;
