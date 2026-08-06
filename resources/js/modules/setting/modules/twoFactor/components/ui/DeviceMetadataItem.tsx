import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";

import { Badge } from "@/shared/components/shadcn/ui/badge";

import { badgeVariants, type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

export interface DeviceMetadataItemProps {
  icon: LucideIcon;
  title: string;
  description: string;
  iconColor?: IconColorVariant;
  badge?: string;
  badgePosition?: "before" | "after";
}

function DeviceMetadataItem({
  icon: Icon,
  title,
  description,
  iconColor = "violet",
  badge,
  badgePosition = "before",
}: DeviceMetadataItemProps): JSX.Element {
  const showBadge = badge !== undefined && badge !== "";

  const colors = iconColorVariants[iconColor];

  return (
    <div className="flex gap-4">
      <div className={cn("flex h-10 w-10 rounded-md p-2", colors.iconBgClass)}>
        <Icon className={cn("h-6 w-6", colors.iconFgClass)} />
      </div>

      <div className="flex flex-col gap-1">
        <span className="text-sm font-medium">{title}</span>

        {showBadge && badgePosition === "before" && (
          <Badge className={cn(badgeVariants.success, "mt-1.5 block")}>{badge}</Badge>
        )}

        <span className="text-sm text-muted-foreground">{description}</span>

        {showBadge && badgePosition === "after" && (
          <Badge className={cn(badgeVariants.success, "mt-1.5 block")}>{badge}</Badge>
        )}
      </div>
    </div>
  );
}

export default DeviceMetadataItem;
