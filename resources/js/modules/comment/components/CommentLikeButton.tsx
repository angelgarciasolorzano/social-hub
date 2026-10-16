import type { JSX } from "react";

import { Heart } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";

import { cn } from "@/shared/lib/utils";

import { useCommentLike } from "../hooks/useCommentLike";
import type { CommentLikesInfo } from "../types/comment";

interface CommentLikeButtonProps {
  commentId: number;
  initialLikesInfo: CommentLikesInfo;
}

function CommentLikeButton({ commentId, initialLikesInfo }: CommentLikeButtonProps): JSX.Element {
  const { error, isPending, likesInfo, toggleLike } = useCommentLike(commentId, initialLikesInfo);

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
