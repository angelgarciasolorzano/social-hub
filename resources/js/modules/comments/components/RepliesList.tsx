import { Dispatch, SetStateAction, useEffect, useRef } from "react";

import { useIntersectionObserver } from "usehooks-ts";

import { usePaginatedComments } from "@/hooks";

import { CommentableType } from "@/enums";

import CommentItem from "./CommentItem";

interface RepliesListProps {
  commentId: number;
  replyTo: number | null;
  setReplyTo: Dispatch<SetStateAction<number | null>>;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
}

function RepliesList({
  commentId,
  replyTo,
  setReplyTo,
  showReplies,
  setShowReplies,
}: RepliesListProps) {
  const scrollRef = useRef<HTMLDivElement | null>(null);
  const loadMoreTimer = useRef<number | null>(null);

  const { commentsPage, isLoadingMore, isRefreshing, loadMoreComments, hasMoreComments } =
    usePaginatedComments(CommentableType.COMMENT, commentId);

  const { isIntersecting, ref: sentinelRef } = useIntersectionObserver({
    threshold: 0,
    root: scrollRef.current,
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
    <div className="ml-4 flex max-h-72 flex-col gap-2 overflow-auto" ref={scrollRef}>
      {commentsPage.data.map((reply) => (
        <CommentItem
          comment={reply}
          isReply
          key={reply.id}
          replyTo={replyTo}
          setReplyTo={setReplyTo}
          setShowReplies={setShowReplies}
          showReplies={showReplies}
        />
      ))}

      <div className="h-1 w-full" ref={sentinelRef} />

      {hasMoreComments && (
        <div className="py-1 text-center text-xs text-gray-500">
          {isLoadingMore ? "Cargando más respuestas..." : "Desliza para cargar más..."}
        </div>
      )}
    </div>
  );
}

export default RepliesList;
