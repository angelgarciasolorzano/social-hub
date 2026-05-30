import type { ReactNode } from "react";
import { Fragment } from "react";

import type { LucideIcon } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/ui/card";
import { Separator } from "@/shared/components/ui/separator";

type BadgeVariant =
  | "default"
  | "destructive"
  | "link"
  | "secondary"
  | "outline"
  | "ghost"
  | null
  | undefined;

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
  iconBgColor: string;
  iconColor: string;
  action?: SumaryCardAction;
}

interface SummaryCardProps {
  title: string;
  data: SumaryCardItem[];
  showLastSeparator?: boolean;
  renderAction?: (action: SumaryCardAction) => ReactNode;
}

function SummaryCard({ title, showLastSeparator, data, renderAction }: SummaryCardProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {data.map((summary, index) => {
          const Icon = summary.icon;

          return (
            <Fragment key={summary.key}>
              <div className="flex items-center gap-4">
                <div className={`flex h-12 w-12 rounded-md p-2 ${summary.iconBgColor}`}>
                  <Icon className={`h-8 w-8 ${summary.iconColor}`} />
                </div>

                <div className="flex w-full items-center justify-between gap-4">
                  <div className="space-y-1">
                    <h4 className="text-sm font-medium">{summary.title}</h4>

                    <p className="text-sm text-muted-foreground">{summary.description}</p>
                  </div>

                  {summary.action && renderAction && (
                    <div className="flex items-center">{renderAction(summary.action)}</div>
                  )}
                </div>
              </div>

              {showLastSeparator && index < data.length - 1 && <Separator />}
            </Fragment>
          );
        })}
      </CardContent>
    </Card>
  );
}

export default SummaryCard;
