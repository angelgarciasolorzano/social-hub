import { useState } from "react";

import { useHttp } from "@inertiajs/react";

import type { LikesInfo, LikeToggleResponse } from "../types/likes";

const TOO_MANY_REQUESTS_STATUS = 429;

interface UseLikeToggleReturn {
  error: string | null;
  isPending: boolean;
  likesInfo: LikesInfo;
  toggleLike: () => void;
}

/**
 * Like or unlike a likeable (post or comment), updating the count and the state at once and reverting them if the request fails.
 * A repeated call while a request is pending is ignored.
 * The initial values are read once per mount; later toggles use the server response.
 *
 * @param toggleUrl URL of the toggle endpoint, e.g. `toggleComment(id).url`.
 */
export function useLikeToggle(toggleUrl: string, initialLikesInfo: LikesInfo): UseLikeToggleReturn {
  const [error, setError] = useState<string | null>(null);

  const {
    data: likesInfo,
    post,
    processing,
    setData,
  } = useHttp<LikesInfo, LikeToggleResponse>(initialLikesInfo);

  const toggleLike = () => {
    if (processing) return;

    setError(null);

    post(toggleUrl, {
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
