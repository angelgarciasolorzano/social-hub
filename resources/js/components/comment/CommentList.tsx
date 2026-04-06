import { Dispatch, SetStateAction, useEffect, useRef, useState } from "react";

import { cn } from "@/shared/lib/utils";
import { useIntersectionObserver } from "usehooks-ts";

import { usePaginatedComments } from "@/hooks";
import { useModal } from "@/shared/hooks/useModal";

import { CommentableType } from "@/enums";

import { Input } from "../ui/input";
import { Separator } from "../ui/separator";
import CommentInputModal from "../../modules/comments/components/CommentInputModal";
import CommentItem from "./CommentItem";

interface CommentListProps {
  postId: number;
}

function CommentList({ postId }: CommentListProps) {
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [showReplies, setShowReplies] = useState<number | null>(null);

  const scrollRef = useRef<HTMLDivElement | null>(null);
  const loadMoreTimer = useRef<number | null>(null);

  const { open, setOpen } = useModal();

  const {
    commentsPage,
    isLoadingMore,
    isRefreshing,
    loadMoreComments,
    hasMoreComments,
    increaseRepliesCount,
    uploadedComments,
  } = usePaginatedComments(CommentableType.POST, postId);

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
      <RenderLoadingState
        open={open}
        postId={postId}
        setOpen={setOpen}
        uploadedComments={uploadedComments}
      />
    );
  }

  if (!commentsPage || !commentsPage.data.length) {
    return (
      <RenderEmptyState
        open={open}
        postId={postId}
        setOpen={setOpen}
        uploadedComments={uploadedComments}
      />
    );
  }

  return (
    <>
      <div className={cn("flex h-full flex-col gap-4 overflow-y-auto pr-2.5")} ref={scrollRef}>
        {commentsPage.data.map((comment) => (
          <CommentItem
            onReplyCreated={increaseRepliesCount}
            comment={comment}
            key={comment.id}
            replyTo={replyTo}
            setReplyTo={setReplyTo}
            setShowReplies={setShowReplies}
            showReplies={showReplies}
            uploadedComments={uploadedComments}
          />
        ))}

        <div className="h-1 w-full" ref={sentinelRef} />

        {hasMoreComments && (
          <div className="py-2 text-center text-sm text-gray-500">
            {isLoadingMore ? "Cargando más..." : "Desliza para cargar más..."}
          </div>
        )}
      </div>

      <RenderCommentInput
        open={open}
        postId={postId}
        setOpen={setOpen}
        uploadedComments={uploadedComments}
      />
    </>
  );
}

type RenderCommentBaseProps = CommentListProps & {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  uploadedComments?: () => void;
};

type RenderLoadingStateProps = RenderCommentBaseProps;

function RenderLoadingState({ open, setOpen, uploadedComments, postId }: RenderLoadingStateProps) {
  return (
    <>
      <div className="flex flex-1 items-center justify-center">
        <p className="text-center text-sm text-gray-600 dark:text-white/90">
          Cargando comentarios...
        </p>
      </div>

      <RenderCommentInput
        open={open}
        postId={postId}
        setOpen={setOpen}
        uploadedComments={uploadedComments}
      />
    </>
  );
}

type RenderEmptyStateProps = RenderCommentBaseProps;

function RenderEmptyState({ postId, open, setOpen, uploadedComments }: RenderEmptyStateProps) {
  return (
    <>
      <div className="flex flex-1 items-center justify-center">
        <p className="text-center text-sm text-gray-600 dark:text-white/90">
          No hay comentarios en esta publicación para mostrar
        </p>
      </div>

      <RenderCommentInput
        open={open}
        postId={postId}
        setOpen={setOpen}
        uploadedComments={uploadedComments}
      />
    </>
  );
}

type RenderCommentInputProps = RenderCommentBaseProps;

function RenderCommentInput(props: RenderCommentInputProps) {
  const { postId, open, setOpen, uploadedComments } = props;

  return (
    <>
      <Separator className="dark:bg-gray-700" />

      <Input
        className="cursor-pointer"
        onClick={() => setOpen(true)}
        autoFocus={false}
        placeholder="Escribe tu comentario"
        readOnly
      />

      <CommentInputModal
        onCommentPosted={uploadedComments}
        onOpenChange={setOpen}
        commentableId={postId}
        commentableType={CommentableType.POST}
        open={open}
        setOpen={setOpen}
        title="Nuevo comentario"
      />
    </>
  );
}

export default CommentList;
