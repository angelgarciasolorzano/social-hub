import type { JSX } from "react";

import { Card, CardContent, CardFooter, CardHeader } from "@/shared/components/shadcn/ui/card";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

function TrustedDeviceSummarySkeleton(): JSX.Element {
  return (
    <Card className="flex flex-col">
      <CardHeader className="items-center gap-2 pb-0">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="h-4 w-56" />
      </CardHeader>

      <CardContent className="flex flex-1 items-center gap-4 pb-0">
        <Skeleton className="h-40 w-40 shrink-0 rounded-full" />

        <div className="flex flex-1 flex-col gap-3">
          <Skeleton className="h-4 w-24" />
          <Skeleton className="h-4 w-32" />
          <Skeleton className="h-4 w-28" />
          <Skeleton className="h-4 w-24" />
        </div>
      </CardContent>

      <CardFooter className="mx-auto">
        <Skeleton className="h-4 w-24" />
      </CardFooter>
    </Card>
  );
}

export default TrustedDeviceSummarySkeleton;
