import type { Dispatch, JSX, SetStateAction } from "react";
import { useEffect, useRef } from "react";

import { useIntersectionObserver } from "usehooks-ts";

import { ScrollArea } from "@/shared/components/shadcn/ui/scroll-area";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { CommentType } from "../enums/commentType";
import { useCommentPaginated } from "../hooks/useCommentPaginated";
import { useCommentScrollAreaViewport } from "../hooks/useCommentScrollAreaViewport";
import CommentItem from "./CommentItem";
import { CommentLoadError, CommentSkeletonList } from "./CommentLoadStates";

interface CommentRepliesListProps {
  commentId: number;
  onReplyCreated?: () => void;
  onReplyDeleted?: () => void;
  refreshCounter: number;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
}

function CommentRepliesList({
  commentId,
  onReplyCreated,
  onReplyDeleted,
  refreshCounter,
  showReplies,
  setShowReplies,
}: CommentRepliesListProps): JSX.Element | null {
  const { scrollAreaRef, viewport } = useCommentScrollAreaViewport();

  const loadMoreTimer = useRef<number | null>(null);

  const {
    commentsPage,
    isLoadingMore,
    isRefreshing,
    loadMoreComments,
    hasMoreComments,
    loadFailure,
    reloadComments,
    retryLoad,
    updateCommentContent,
    uploadedComments,
  } = useCommentPaginated(CommentType.COMMENT, commentId);

  const { isIntersecting, ref: sentinelRef } = useIntersectionObserver({
    threshold: 0,
    root: viewport,
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
    <ScrollArea
      className="mt-4 ml-4 *:data-[slot=scroll-area-viewport]:max-h-72"
      ref={scrollAreaRef}
    >
      <div className="flex flex-col gap-4 pr-2.5">
        {commentsPage.data.map((reply) => (
          <CommentItem
            comment={reply}
            isReply
            key={reply.id}
            onContentUpdated={updateCommentContent}
            onDeleted={() => {
              reloadComments();
              onReplyDeleted?.();
            }}
            onReplyCreated={onReplyCreated}
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
          <div className="flex items-center justify-center gap-2 py-1 text-xs text-muted-foreground">
            {isLoadingMore ? (
              <>
                <Spinner />
                Cargando más respuestas...
              </>
            ) : (
              "Desliza para cargar más..."
            )}
          </div>
        )}
      </div>
    </ScrollArea>
  );
}

export default CommentRepliesList;
