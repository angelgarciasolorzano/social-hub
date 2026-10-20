import type { JSX } from "react";

import { Heart } from "lucide-react";

import { toggleComment } from "@/shared/wayfinder/actions/App/Modules/Like/Controllers/LikeController";

import { Button } from "@/shared/components/shadcn/ui/button";

import { useLikeToggle } from "@/shared/hooks";

import { cn } from "@/shared/lib/utils";

import type { LikesInfo } from "@/shared/types/likes";

interface CommentLikeButtonProps {
  commentId: number;
  initialLikesInfo: LikesInfo;
}

function CommentLikeButton({ commentId, initialLikesInfo }: CommentLikeButtonProps): JSX.Element {
  const { error, isPending, likesInfo, toggleLike } = useLikeToggle(
    toggleComment(commentId).url,
    initialLikesInfo,
  );

  return (
    <>
      <Button
        aria-pressed={likesInfo.likedByMe}
        className={cn(
          "cursor-pointer text-muted-foreground hover:text-destructive",
          likesInfo.likedByMe && "text-destructive",
        )}
        disabled={isPending}
        onClick={toggleLike}
        size="xs"
        type="button"
        variant="ghost"
      >
        <Heart aria-hidden="true" className={cn(likesInfo.likedByMe && "fill-current")} />
        Me gusta
        {likesInfo.count > 0 && <span>{likesInfo.count}</span>}
      </Button>

      {error && (
        <span className="ml-1 text-destructive" role="alert">
          {error}
        </span>
      )}
    </>
  );
}

export default CommentLikeButton;
