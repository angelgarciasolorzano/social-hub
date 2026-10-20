import { useState } from "react";

import { useHttp } from "@inertiajs/react";

import { toggleComment } from "@/shared/wayfinder/actions/App/Modules/Like/Controllers/LikeController";

import type { CommentLikesInfo, CommentLikeToggleResponse } from "../types/comment";

const TOO_MANY_REQUESTS_STATUS = 429;

interface UseCommentLikeReturn {
  error: string | null;
  isPending: boolean;
  likesInfo: CommentLikesInfo;
  toggleLike: () => void;
}

/**
 * Like or unlike a comment, updating the count and the state at once and reverting them if the request fails.
 * A repeated call while a request is pending is ignored.
 * The initial values are read once per mount; later toggles use the server response.
 */
export function useCommentLike(
  commentId: number,
  initialLikesInfo: CommentLikesInfo,
): UseCommentLikeReturn {
  const [error, setError] = useState<string | null>(null);

  const {
    data: likesInfo,
    post,
    processing,
    setData,
  } = useHttp<CommentLikesInfo, CommentLikeToggleResponse>(initialLikesInfo);

  const toggleLike = () => {
    if (processing) return;

    setError(null);

    post(toggleComment(commentId).url, {
      optimistic: (currentLikesInfo) => ({
        count: currentLikesInfo.count + (currentLikesInfo.likedByMe ? -1 : 1),
        likedByMe: !currentLikesInfo.likedByMe,
      }),
      onSuccess: (response) => {
        setData({ count: response.likesCount, likedByMe: response.liked });
      },
      onHttpException: (response) => {
        setError(
          response.status === TOO_MANY_REQUESTS_STATUS
            ? "Demasiados intentos, espera un momento."
            : "No se pudo actualizar tu me gusta.",
        );

        return false;
      },
      onNetworkError: () => {
        setError("No se pudo actualizar tu me gusta.");

        return false;
      },
    }).catch(() => undefined);
  };

  return { error, isPending: processing, likesInfo, toggleLike };
}
