import type { JSX } from "react";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

function FriendRequestsHighlightSkeleton(): JSX.Element {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-4 rounded-xl border p-4" role="status">
      <span className="sr-only">{t("friendship/friends.states.loading")}</span>

      <Skeleton aria-hidden="true" className="h-8 w-48" />

      <div aria-hidden="true" className="grid gap-3 @2xl/friends:grid-cols-2">
        <Skeleton className="h-16 rounded-md" />

        <Skeleton className="h-16 rounded-md" />
      </div>
    </div>
  );
}

export default FriendRequestsHighlightSkeleton;
