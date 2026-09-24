import type { JSX } from "react";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

function TrustedDevicesStatCardsSkeleton(): JSX.Element {
  const skeletonKeys = ["total", "active", "expiring", "recent"];

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {skeletonKeys.map((skeletonKey) => (
        <div
          className="flex h-full min-w-0 items-start gap-4 rounded-xl border bg-card p-4 shadow-sm"
          key={skeletonKey}
        >
          <Skeleton className="h-12 w-12 shrink-0 rounded-md" />

          <div className="flex min-w-0 flex-1 flex-col gap-2">
            <Skeleton className="h-8 w-20 max-w-full" />
            <Skeleton className="h-4 w-36 max-w-full" />
            <Skeleton className="h-8 w-full max-w-full" />
          </div>
        </div>
      ))}
    </div>
  );
}

export default TrustedDevicesStatCardsSkeleton;
