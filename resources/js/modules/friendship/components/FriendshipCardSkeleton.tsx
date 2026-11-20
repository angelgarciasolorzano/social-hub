import type { JSX } from "react";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

interface FriendshipCardSkeletonProps {
  className?: string;
  count?: number;
}

function FriendshipCardSkeleton(props: FriendshipCardSkeletonProps): JSX.Element {
  const { className, count = 6 } = props;

  const { t } = useTranslation();

  return (
    <div className={className} role="status">
      <span className="sr-only">{t("friendship/friends.states.loading")}</span>

      {Array.from({ length: count }, (_, position) => (
        <div
          aria-hidden="true"
          className="flex flex-col overflow-hidden rounded-xl border bg-card"
          key={position}
        >
          <Skeleton className="h-16 rounded-none" />

          <div className="-mt-7 flex flex-col gap-3 px-4 pb-4">
            <Skeleton className="size-14 rounded-full border-3 border-card" />

            <div className="space-y-1.5">
              <Skeleton className="h-4 w-2/3" />
              <Skeleton className="h-3 w-1/2" />
            </div>

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
