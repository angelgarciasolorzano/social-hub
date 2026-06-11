import type { Dispatch, SetStateAction } from "react";
import { useEffect, useRef, useState } from "react";

import { useIntersectionObserver } from "usehooks-ts";

import { CommentableType } from "../enums/commentableType";
import { usePaginatedComments } from "../hooks/usePaginatedComments";
import CommentItem from "./CommentItem";

interface RepliesListProps {
  commentId: number;
  refreshCounter: number;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
}

function RepliesList({ commentId, refreshCounter, showReplies, setShowReplies }: RepliesListProps) {
  const [scrollRoot, setScrollRoot] = useState<HTMLDivElement | null>(null);

  const loadMoreTimer = useRef<number | null>(null);

  const {
    commentsPage,
    isLoadingMore,
    isRefreshing,
    loadMoreComments,
    hasMoreComments,
    uploadedComments,
  } = usePaginatedComments(CommentableType.COMMENT, commentId);

  const { isIntersecting, ref: sentinelRef } = useIntersectionObserver({
    threshold: 0,
    root: scrollRoot,
    rootMargin: "200px",
  });

  useEffect(() => {
    if (!isIntersecting || isRefreshing || isLoadingMore || !hasMoreComments) return;
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
  }, [loadMoreComments, isIntersecting, isRefreshing, isLoadingMore, hasMoreComments]);

  useEffect(() => {
    if (refreshCounter === 0) return;

    uploadedComments();
  }, [refreshCounter, uploadedComments]);

  if (isRefreshing && !commentsPage) {
    return (
      <div className="mt-4 flex flex-1 items-center justify-center">
        <p className="text-center text-xs text-gray-500 dark:text-white/80">
          Cargando respuestas...
        </p>
      </div>
    );
  }

  if (!commentsPage) return null;

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

      {hasMoreComments && (
        <div className="py-1 text-center text-xs text-gray-500">
          {isLoadingMore ? "Cargando más respuestas..." : "Desliza para cargar más..."}
        </div>
      )}
    </div>
  );
}

export default RepliesList;
