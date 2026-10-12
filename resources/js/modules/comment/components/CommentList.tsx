import type { Dispatch, JSX, SetStateAction } from "react";
import { useEffect, useRef, useState } from "react";

import { useIntersectionObserver } from "usehooks-ts";

import { Input } from "@/shared/components/shadcn/ui/input";
import { ScrollArea } from "@/shared/components/shadcn/ui/scroll-area";
import { Separator } from "@/shared/components/shadcn/ui/separator";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { useDialog } from "@/shared/hooks/useDialog";

import { CommentType } from "../enums/commentType";
import { useCommentPaginated } from "../hooks/useCommentPaginated";
import { useScrollAreaViewport } from "../hooks/useScrollAreaViewport";
import type { CommentContextPreview } from "../types/comment";
import CommentItem from "./CommentItem";
import { CommentLoadError, CommentSkeletonList } from "./CommentLoadStates";
import CommentInputDialog from "./dialog/CommentInputDialog";

interface CommentListProps {
  postId: number;
  previewContext: CommentContextPreview;
}

function CommentList({ postId, previewContext }: CommentListProps): JSX.Element {
  const [showReplies, setShowReplies] = useState<number | null>(null);

  const { scrollAreaRef, viewport } = useScrollAreaViewport();

  const loadMoreTimer = useRef<number | null>(null);

  const { open: openModalComment, setOpen: setOpenModalComment } = useDialog();

  const {
    commentsPage,
    isLoadingMore,
    isRefreshing,
    loadMoreComments,
    hasMoreComments,
    increaseRepliesCount,
    loadFailure,
    retryLoad,
    uploadedComments,
  } = useCommentPaginated(CommentType.POST, postId);

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

  if (!commentsPage && loadFailure !== null) {
    return (
      <RenderErrorState
        onRetry={retryLoad}
        openModalComment={openModalComment}
        postId={postId}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        uploadedComments={uploadedComments}
      />
    );
  }

  if (!commentsPage?.data.length) {
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
      <ScrollArea className="min-h-0 flex-1" ref={scrollAreaRef}>
        <div className="flex flex-col gap-6 pr-2.5">
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

          {loadFailure !== null && (
            <CommentLoadError
              message={
                loadFailure === "more"
                  ? "No se pudieron cargar más comentarios."
                  : "No se pudieron actualizar los comentarios."
              }
              onRetry={retryLoad}
            />
          )}

          {hasMoreComments && loadFailure === null && (
            <div className="flex items-center justify-center gap-2 py-2 text-sm text-muted-foreground">
              {isLoadingMore ? (
                <>
                  <Spinner />
                  Cargando más...
                </>
              ) : (
                "Desliza para cargar más..."
              )}
            </div>
          )}
        </div>
      </ScrollArea>

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
      <div className="flex flex-1 flex-col overflow-hidden">
        <CommentSkeletonList commentSkeletonCount={3} label="Cargando comentarios..." />
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

type RenderErrorStateProps = RenderCommentBaseProps & {
  onRetry: () => void;
};

function RenderErrorState({
  onRetry,
  openModalComment,
  setOpenModalComment,
  uploadedComments,
  postId,
  previewContext,
}: RenderErrorStateProps) {
  return (
    <>
      <div className="flex flex-1 items-center justify-center">
        <CommentLoadError message="No se pudieron cargar los comentarios." onRetry={onRetry} />
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
        <p className="text-center text-sm text-muted-foreground">
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
        onClick={() => {
          setOpenModalComment(true);
        }}
        autoFocus={false}
        placeholder="Escribe tu comentario"
        readOnly
      />

      <CommentInputDialog
        onOpenChange={setOpenModalComment}
        commentableId={postId}
        commentableType={CommentType.POST}
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
