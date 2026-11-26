import type { JSX } from "react";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

interface FriendshipCardSkeletonProps {
  className?: string;
  count?: number;
  label?: string;
}

function FriendshipCardSkeleton(props: FriendshipCardSkeletonProps): JSX.Element {
  const { className, count = 6, label } = props;

  const { t } = useTranslation();

  return (
    <div className={className} role="status">
      <span className="sr-only">{label ?? t("friendship/friends.states.loading")}</span>

      {Array.from({ length: count }, (_, position) => (
        <div
          aria-hidden="true"
          className="flex flex-col overflow-hidden rounded-xl border bg-card"
          key={position}
        >
          <div className="relative aspect-[16/10]">
            <Skeleton className="absolute inset-0 rounded-none" />

            <div className="absolute inset-x-4 bottom-3.5 flex items-center gap-3">
              <Skeleton className="size-11 shrink-0 rounded-full bg-foreground/10" />

              <div className="flex-1 space-y-1.5">
                <Skeleton className="h-4 w-2/3 bg-foreground/10" />
                <Skeleton className="h-3 w-1/2 bg-foreground/10" />
              </div>
            </div>
          </div>

          <div className="flex flex-col gap-3 p-4">
            <Skeleton className="h-3 w-1/3" />

            <div className="flex gap-2">
              <Skeleton className="h-8 flex-1" />
              <Skeleton className="h-8 w-24" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

export default FriendshipCardSkeleton;
