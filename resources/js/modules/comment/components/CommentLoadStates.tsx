import type { JSX } from "react";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { cn } from "@/shared/lib/utils";

interface CommentSkeletonListProps {
  className?: string;
  commentSkeletonCount: number;
  isReply?: boolean;
  label: string;
}

export function CommentSkeletonList({
  className,
  commentSkeletonCount,
  isReply = false,
  label,
}: CommentSkeletonListProps): JSX.Element {
  return (
    <div aria-busy="true" className={cn("flex flex-col gap-4", className)} role="status">
      <span className="sr-only">{label}</span>

      {Array.from({ length: commentSkeletonCount }, (_, skeletonPosition) => (
        <div
          className={cn(
            "flex flex-col gap-3 p-2",
            !isReply && "rounded-md border dark:border-[#343434]",
          )}
          key={skeletonPosition}
        >
          <div className="flex items-center gap-2">
            <Skeleton className="size-8 rounded-full" />

            <div className="flex flex-col gap-1.5">
              <Skeleton className="h-3 w-28" />
              <Skeleton className="h-2.5 w-20" />
            </div>
          </div>

          <Skeleton className="h-3 w-full" />
          <Skeleton className="h-3 w-2/3" />
        </div>
      ))}
    </div>
  );
}

interface CommentLoadErrorProps {
  className?: string;
  message: string;
  onRetry: () => void;
}

export function CommentLoadError({
  className,
  message,
  onRetry,
}: CommentLoadErrorProps): JSX.Element {
  return (
    <div
      className={cn("flex flex-col items-center gap-2 py-2 text-center", className)}
      role="alert"
    >
      <p className="text-sm text-gray-600 dark:text-white/90">{message}</p>

      <Button
        className="cursor-pointer"
        onClick={onRetry}
        size="sm"
        type="button"
        variant="outline"
      >
        Reintentar
      </Button>
    </div>
  );
}
