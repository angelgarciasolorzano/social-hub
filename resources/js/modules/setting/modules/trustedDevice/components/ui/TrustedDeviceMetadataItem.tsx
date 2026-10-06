import type { JSX, ReactNode } from "react";

import { Badge } from "@/shared/components/shadcn/ui/badge";

import { badgeVariants, type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

export interface TrustedDeviceMetadataItemProps {
  icon: ReactNode;
  title: string;
  description?: string | null;
  iconColor?: IconColorVariant;
  badge?: string;
  badgePosition?: "before" | "after";
}

function TrustedDeviceMetadataItem({
  icon,
  title,
  description,
  iconColor = "violet",
  badge,
  badgePosition = "before",
}: TrustedDeviceMetadataItemProps): JSX.Element | null {
  const showBadge = badge !== undefined && badge.trim() !== "";
  const hasTitle = title.trim() !== "";
  const hasDescription =
    description !== undefined && description !== null && description.trim() !== "";

  const colors = iconColorVariants[iconColor];

  if (!hasTitle || (!hasDescription && !showBadge)) {
    return null;
  }

  return (
    <div className="flex gap-4">
      <div aria-hidden="true" className={cn("flex h-10 w-10 rounded-md p-2", colors.iconBgClass)}>
        {icon}
      </div>

      <dl className="flex flex-col gap-1">
        <dt className="text-sm font-medium">{title}</dt>

        {showBadge && badgePosition === "before" && (
          <dd className="m-0">
            <Badge className={cn(badgeVariants.success, "mt-1.5 block")}>{badge}</Badge>
          </dd>
        )}

        {hasDescription && <dd className="m-0 text-sm text-muted-foreground">{description}</dd>}

        {showBadge && badgePosition === "after" && (
          <dd className="m-0">
            <Badge className={cn(badgeVariants.success, "mt-1.5 block")}>{badge}</Badge>
          </dd>
        )}
      </dl>
    </div>
  );
}

export default TrustedDeviceMetadataItem;
