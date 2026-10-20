import type { JSX } from "react";

import { Heart } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";

import { cn } from "@/shared/lib/utils";

interface PostLikeButtonProps {
  error: string | null;
  isPending: boolean;
  likedByMe: boolean;
  onToggle: () => void;
}

function PostLikeButton({
  error,
  isPending,
  likedByMe,
  onToggle,
}: PostLikeButtonProps): JSX.Element {
  return (
    <div className="flex items-center gap-2">
      <Button
        aria-pressed={likedByMe}
        className={cn("cursor-pointer", likedByMe && "text-destructive")}
        disabled={isPending}
        onClick={onToggle}
        type="button"
        variant="outline"
      >
        <Heart aria-hidden="true" className={cn(likedByMe && "fill-current")} />
        Me gusta
      </Button>

      {error && (
        <span className="text-sm text-destructive" role="alert">
          {error}
        </span>
      )}
    </div>
  );
}

export default PostLikeButton;
