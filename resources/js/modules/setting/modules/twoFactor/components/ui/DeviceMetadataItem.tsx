import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";

import { Badge } from "@/shared/components/shadcn/ui/badge";

export interface DeviceMetadataItemProps {
  icon: LucideIcon;
  title: string;
  description: string;
  iconBgClass?: string;
  iconFgClass?: string;
  badge?: string;
  badgePosition?: "before" | "after";
}

function DeviceMetadataItem({
  icon: Icon,
  title,
  description,
  iconBgClass = "bg-violet-100/50 dark:bg-violet-900/20",
  iconFgClass = "text-violet-700 dark:text-violet-500",
  badge,
  badgePosition = "before",
}: DeviceMetadataItemProps): JSX.Element {
  const showBadge = badge !== undefined && badge !== "";

  return (
    <div className="flex gap-4">
      <div className={`flex h-10 w-10 rounded-md p-2 ${iconBgClass}`}>
        <Icon className={`h-6 w-6 ${iconFgClass}`} />
      </div>

      <div className="flex flex-col gap-1">
        <span className="text-sm font-medium">{title}</span>

        {showBadge && badgePosition === "before" && (
          <Badge className="mt-1.5 block bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300">
            {badge}
          </Badge>
        )}

        <span className="text-sm text-muted-foreground">{description}</span>

        {showBadge && badgePosition === "after" && (
          <Badge className="mt-1.5 block bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300">
            {badge}
          </Badge>
        )}
      </div>
    </div>
  );
}

export default DeviceMetadataItem;
