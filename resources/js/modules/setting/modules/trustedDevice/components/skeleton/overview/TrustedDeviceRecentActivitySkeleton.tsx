import type { JSX } from "react";

import { Card, CardContent, CardFooter, CardHeader } from "@/shared/components/shadcn/ui/card";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

function TrustedDeviceRecentActivitySkeleton(): JSX.Element {
  const activitySkeletonKeys = ["first", "second", "third"];

  return (
    <Card>
      <CardHeader>
        <Skeleton className="h-5 w-40" />
      </CardHeader>

      <CardContent className="space-y-5">
        {activitySkeletonKeys.map((activitySkeletonKey) => (
          <div className="flex items-center justify-between gap-4" key={activitySkeletonKey}>
            <div className="flex items-start gap-4">
              <Skeleton className="h-10 w-10 shrink-0 rounded-full" />

              <div className="space-y-2">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-4 w-24" />
              </div>
            </div>

            <Skeleton className="h-4 w-16" />
          </div>
        ))}
      </CardContent>

      <CardFooter className="mx-auto">
        <Skeleton className="h-4 w-36" />
      </CardFooter>
    </Card>
  );
}

export default TrustedDeviceRecentActivitySkeleton;
