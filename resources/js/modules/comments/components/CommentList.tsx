import type { Dispatch, SetStateAction } from "react";
import { useEffect, useRef, useState } from "react";

import { useIntersectionObserver } from "usehooks-ts";

import { Input } from "@/shared/components/shadcn/ui/input";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { useModal } from "@/shared/hooks/useModal";

import { cn } from "@/shared/lib/utils";

import { CommentableType } from "../enums/commentableType";
import { usePaginatedComments } from "../hooks/usePaginatedComments";
import type { CommentContextPreview } from "../types/comment";
import CommentItem from "./CommentItem";
import CommentInputDialog from "./modal/CommentInputDialog";

interface CommentListProps {
  postId: number;
  previewContext: CommentContextPreview;
}

function CommentList({ postId, previewContext }: CommentListProps) {
  const [showReplies, setShowReplies] = useState<number | null>(null);

  const [scrollRoot, setScrollRoot] = useState<HTMLDivElement | null>(null);
  const loadMoreTimer = useRef<number | null>(null);

  const { open: openModalComment, setOpen: setOpenModalComment } = useModal();

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

  if (isRefreshing && !commentsPage) {
    return (
      <RenderLoadingState
        openModalComment={openModalComment}
        postId={postId}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        uploadedComments={uploadedComments}
      />
    );
  }

  if (!commentsPage || !commentsPage.data.length) {
    return (
      <RenderEmptyState
        openModalComment={openModalComment}
        postId={postId}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        uploadedComments={uploadedComments}
      />
    );
  }

  return (
    <>
      <div className={cn("flex h-full flex-col gap-6 overflow-y-auto pr-2.5")} ref={setScrollRoot}>
        {commentsPage.data.map((comment) => (
          <CommentItem
            onReplyCreated={increaseRepliesCount}
            comment={comment}
            key={comment.id}
            setShowReplies={setShowReplies}
            showReplies={showReplies}
            uploadedComments={uploadedComments}
          />
        ))}

        <div className="w-full" ref={sentinelRef} />

        {hasMoreComments && (
          <div className="py-2 text-center text-sm text-gray-500">
            {isLoadingMore ? "Cargando más..." : "Desliza para cargar más..."}
          </div>
        )}
      </div>

      <RenderCommentInput
        openModalComment={openModalComment}
        postId={postId}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        uploadedComments={uploadedComments}
      />
    </>
  );
}

type RenderCommentBaseProps = CommentListProps & {
  openModalComment: boolean;
  setOpenModalComment: Dispatch<SetStateAction<boolean>>;
  uploadedComments: (() => void) | undefined;
};

type RenderLoadingStateProps = RenderCommentBaseProps;

function RenderLoadingState({
  openModalComment,
  setOpenModalComment,
  uploadedComments,
  postId,
  previewContext,
}: RenderLoadingStateProps) {
  return (
    <>
      <div className="flex flex-1 items-center justify-center">
        <p className="text-center text-sm text-gray-600 dark:text-white/90">
          Cargando comentarios...
        </p>
      </div>

      <RenderCommentInput
        openModalComment={openModalComment}
        postId={postId}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        uploadedComments={uploadedComments}
      />
    </>
  );
}

type RenderEmptyStateProps = RenderCommentBaseProps;

function RenderEmptyState({
  postId,
  openModalComment,
  setOpenModalComment,
  uploadedComments,
  previewContext,
}: RenderEmptyStateProps) {
  return (
    <>
      <div className="flex flex-1 items-center justify-center">
        <p className="text-center text-sm text-gray-600 dark:text-white/90">
          No hay comentarios en esta publicación para mostrar
        </p>
      </div>

      <RenderCommentInput
        openModalComment={openModalComment}
        postId={postId}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        uploadedComments={uploadedComments}
      />
    </>
  );
}

type RenderCommentInputProps = RenderCommentBaseProps;

function RenderCommentInput(props: RenderCommentInputProps) {
  const { postId, openModalComment, setOpenModalComment, uploadedComments, previewContext } = props;

  return (
    <>
      <Separator />

      <Input
        className="cursor-pointer"
        onClick={() => setOpenModalComment(true)}
        autoFocus={false}
        placeholder="Escribe tu comentario"
        readOnly
      />

      <CommentInputDialog
        onOpenChange={setOpenModalComment}
        commentableId={postId}
        commentableType={CommentableType.POST}
        openModalComment={openModalComment}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        title="Nuevo comentario"
        uploadedComments={uploadedComments}
      />
    </>
  );
}

export default CommentList;
