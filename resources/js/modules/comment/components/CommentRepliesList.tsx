import type { Dispatch, SetStateAction } from "react";
import { useEffect, useRef, useState } from "react";

import { useIntersectionObserver } from "usehooks-ts";

import { CommentType } from "../enums/commentType";
import { usePaginatedComments } from "../hooks/usePaginatedComments";
import CommentItem from "./CommentItem";
import { CommentLoadError, CommentSkeletonList } from "./CommentLoadStates";

interface CommentRepliesListProps {
  commentId: number;
  refreshCounter: number;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
}

function CommentRepliesList({
  commentId,
  refreshCounter,
  showReplies,
  setShowReplies,
}: CommentRepliesListProps) {
  const [scrollRoot, setScrollRoot] = useState<HTMLDivElement | null>(null);

  const loadMoreTimer = useRef<number | null>(null);

  const {
    commentsPage,
    isLoadingMore,
    isRefreshing,
    loadMoreComments,
    hasMoreComments,
    loadFailure,
    retryLoad,
    uploadedComments,
  } = usePaginatedComments(CommentType.COMMENT, commentId);

  const { isIntersecting, ref: sentinelRef } = useIntersectionObserver({
    threshold: 0,
    root: scrollRoot,
    rootMargin: "200px",
  });

  useEffect(() => {
    if (
      !isIntersecting ||
      isRefreshing ||
      isLoadingMore ||
      !hasMoreComments ||
      loadFailure !== null
    ) {
      return;
    }
    if (loadMoreTimer.current !== null) return;

    loadMoreTimer.current = window.setTimeout(() => {
      loadMoreTimer.current = null;
      loadMoreComments();
    }, 300);

    return () => {
      if (loadMoreTimer.current !== null) {
        clearTimeout(loadMoreTimer.current);
        loadMoreTimer.current = null;
      }
    };
  }, [loadMoreComments, isIntersecting, isRefreshing, isLoadingMore, hasMoreComments, loadFailure]);

  useEffect(() => {
    if (refreshCounter === 0) return;

    uploadedComments();
  }, [refreshCounter, uploadedComments]);

  if (isRefreshing && !commentsPage) {
    return (
      <CommentSkeletonList
        className="mt-4 ml-4"
        commentSkeletonCount={2}
        isReply
        label="Cargando respuestas..."
      />
    );
  }

  if (!commentsPage) {
    return loadFailure === null ? null : (
      <CommentLoadError
        className="mt-4 ml-4"
        message="No se pudieron cargar las respuestas."
        onRetry={retryLoad}
      />
    );
  }

  return (
    <div className="mt-4 ml-4 flex max-h-72 flex-col gap-4 overflow-auto" ref={setScrollRoot}>
      {commentsPage.data.map((reply) => (
        <CommentItem
          comment={reply}
          isReply
          key={reply.id}
          setShowReplies={setShowReplies}
          showReplies={showReplies}
          uploadedComments={uploadedComments}
        />
      ))}

      <div className="w-full" ref={sentinelRef} />

      {loadFailure !== null && (
        <CommentLoadError
          message={
            loadFailure === "more"
              ? "No se pudieron cargar más respuestas."
              : "No se pudieron actualizar las respuestas."
          }
          onRetry={retryLoad}
        />
      )}

      {hasMoreComments && loadFailure === null && (
        <div className="py-1 text-center text-xs text-gray-500">
          {isLoadingMore ? "Cargando más respuestas..." : "Desliza para cargar más..."}
        </div>
      )}
    </div>
  );
}

export default CommentRepliesList;
