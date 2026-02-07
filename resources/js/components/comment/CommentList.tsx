import { useEffect, useRef, useState } from "react";

import { cn } from "@/lib/utils";
import { useIntersectionObserver } from "usehooks-ts";

import { usePaginatedComments } from "@/hooks";

import { CommentableType } from "@/enums";

import { Separator } from "../ui/separator";
import CommentForm from "./CommentForm";
import CommentItem from "./CommentItem";

interface CommentListProps {
  postId: number;
}

function CommentList({ postId }: CommentListProps) {
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [showReplies, setShowReplies] = useState<number | null>(null);
  const scrollRef = useRef<HTMLDivElement | null>(null);
  const loadMoreTimer = useRef<number | null>(null);

  const { commentsPage, loading, loadMoreComments, hasMoreComments, uploadedComments } =
    usePaginatedComments(CommentableType.POST, postId);

  const { isIntersecting, ref: sentinelRef } = useIntersectionObserver({
    threshold: 0,
    root: scrollRef.current,
    rootMargin: "200px",
  });

  useEffect(() => {
    if (!isIntersecting || loading || !hasMoreComments) return;
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
  }, [loadMoreComments, isIntersecting, loading, hasMoreComments]);

  if (loading && !commentsPage) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-center text-sm text-gray-600 dark:text-white/90">
          Cargando comentarios...
        </p>
      </div>
    );
  }

  if (!commentsPage) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-center text-sm text-gray-600 dark:text-white/90">
          No hay comentarios en esta publicación para mostrar
        </p>
      </div>
    );
  }

  return (
    <>
      <div className={cn("flex h-full flex-col gap-4 overflow-y-auto pr-2.5")} ref={scrollRef}>
        {commentsPage.data.map((comment) => (
          <CommentItem
            comment={comment}
            key={comment.id}
            replyTo={replyTo}
            setReplyTo={setReplyTo}
            setShowReplies={setShowReplies}
            showReplies={showReplies}
          />
        ))}

        <div className="h-1 w-full" ref={sentinelRef} />

        {(loading || hasMoreComments) && (
          <div className="py-2 text-center text-sm text-gray-500">
            {loading ? "Cargando más..." : "Desliza para cargar más..."}
          </div>
        )}
      </div>

      <Separator className="dark:bg-gray-700" />

      <CommentForm
        onCommentPosted={uploadedComments}
        commentableId={postId}
        commentableType={CommentableType.POST}
      />
    </>
  );
}

export default CommentList;
