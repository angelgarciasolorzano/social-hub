import type { Dispatch, JSX, SetStateAction } from "react";
import { useState } from "react";

import { IoMdTime } from "react-icons/io";

import dayjs from "dayjs";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent } from "@/shared/components/shadcn/ui/card";

import { useDialog } from "@/shared/hooks/useDialog";

import { validImage } from "@/shared/lib";
import { cn } from "@/shared/lib/utils";

import type { User } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import { CommentType } from "../enums/commentType";
import type { Comment, CommentContextPreview } from "../types/comment";
import CommentActionsMenu from "./CommentActionsMenu";
import CommentLikeButton from "./CommentLikeButton";
import CommentRepliesList from "./CommentRepliesList";
import CommentInputDialog from "./dialog/CommentInputDialog";

interface CommentItemProps {
  comment: Comment;
  isReply?: boolean;
  onContentUpdated?: (commentId: number, content: string) => void;
  onDeleted?: () => void;
  onReplyCreated?: (commentId: number) => void;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
  uploadedComments?: () => void;
}

type LiveComment = Comment & { content: string; user: User };

type LiveCommentItemProps = CommentItemProps & { comment: LiveComment };

function isLiveComment(comment: Comment): comment is LiveComment {
  return !comment.isDeleted && comment.content !== null && comment.user !== null;
}

function CommentItem(props: CommentItemProps): JSX.Element {
  const { comment } = props;

  if (!isLiveComment(comment)) return <DeletedCommentItem {...props} />;

  return <LiveCommentItem {...props} comment={comment} />;
}

function DeletedCommentItem({
  comment,
  isReply = false,
  onDeleted,
  setShowReplies,
  showReplies,
}: CommentItemProps): JSX.Element {
  return (
    <article className={cn("space-y-1", isReply && "ml-2")}>
      <Card className="gap-0 rounded-md bg-muted/50 py-0 shadow-none">
        <CardContent className="p-2">
          <p className="text-sm text-muted-foreground italic">Comentario eliminado</p>
        </CardContent>
      </Card>

      {comment.repliesInfo.hasReplies && (
        <footer className="-ml-2 flex items-center text-xs font-medium text-muted-foreground">
          <CommentRepliesToggle
            comment={comment}
            setShowReplies={setShowReplies}
            showReplies={showReplies}
          />
        </footer>
      )}

      {showReplies === comment.id && (
        <CommentRepliesList
          commentId={comment.id}
          onReplyDeleted={onDeleted}
          refreshCounter={0}
          setShowReplies={setShowReplies}
          showReplies={showReplies}
        />
      )}
    </article>
  );
}

interface CommentRepliesToggleProps {
  comment: Comment;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
}

function CommentRepliesToggle({
  comment,
  setShowReplies,
  showReplies,
}: CommentRepliesToggleProps): JSX.Element {
  return (
    <Button
      className="cursor-pointer text-muted-foreground"
      onClick={() => {
        setShowReplies(showReplies === comment.id ? null : comment.id);
      }}
      size="xs"
      type="button"
      variant="ghost"
    >
      {showReplies === comment.id
        ? "Ocultar respuestas"
        : `Mostrar ${String(comment.repliesInfo.repliesCount)} respuestas`}
    </Button>
  );
}

function LiveCommentItem(props: LiveCommentItemProps): JSX.Element {
  const {
    comment,
    isReply = false,
    onContentUpdated,
    onDeleted,
    onReplyCreated,
    setShowReplies,
    showReplies,
    uploadedComments,
  } = props;

  const { open: openModalComment, setOpen: setOpenModalComment } = useDialog();

  const [repliesRefreshCounter, setRepliesRefreshCounter] = useState<number>(0);

  const createdAt = dayjs(comment.createdAt).fromNow();

  const previewContext: CommentContextPreview = {
    authorName: comment.user.name,
    authorProfilePicture: comment.user.profilePicture,
    content: comment.content,
    kind: CommentType.COMMENT,
    createdAt: createdAt + " - " + dayjs(comment.createdAt).format("D [de] MMMM [del] YYYY"),
  };

  return (
    <article className={cn("space-y-1", isReply && "ml-2")}>
      <Card
        className={cn(
          "gap-0 rounded-md bg-muted/50 py-0 shadow-none",
          isReply && "border-0 bg-transparent",
        )}
      >
        <CardContent className="flex flex-col gap-2 p-2">
          <header className="flex items-center gap-2">
            <Avatar>
              <AvatarImage
                alt="Foto de perfil"
                src={validImage(comment.user.profilePicture, profilePicturePlaceholder)}
              />

              <AvatarFallback>Foto de perfil</AvatarFallback>
            </Avatar>

            <div className="block">
              <p className="text-sm font-bold">{comment.user.name}</p>

              <div className="flex items-center gap-1 text-xs text-muted-foreground">
                <time className="flex items-center gap-1">
                  <IoMdTime className="h-3 w-3" />
                  {createdAt}
                </time>

                {comment.editedAt && (
                  <span
                    title={`Editado el ${dayjs(comment.editedAt).format("D [de] MMMM [del] YYYY, HH:mm")}`}
                  >
                    · editado
                  </span>
                )}
              </div>
            </div>

            <CommentActionsMenu
              comment={comment}
              onDeleted={() => {
                onDeleted?.();
              }}
              onUpdated={(content) => {
                onContentUpdated?.(comment.id, content);
              }}
            />
          </header>

          <section>
            {comment.repliedTo && (
              <p className="mb-1 text-xs text-muted-foreground">
                Respondiendo a <strong>@{comment.repliedTo.name}</strong>
              </p>
            )}

            <p className="text-sm">{comment.content}</p>
          </section>
        </CardContent>
      </Card>

      <footer className="-ml-2 flex items-center text-xs font-medium text-muted-foreground">
        <CommentLikeButton commentId={comment.id} initialLikesInfo={comment.likesInfo} />

        <Button
          className="cursor-pointer text-muted-foreground"
          onClick={() => {
            setOpenModalComment(true);
          }}
          size="xs"
          type="button"
          variant="ghost"
        >
          Responder
        </Button>

        {!isReply && comment.repliesInfo.hasReplies && (
          <CommentRepliesToggle
            comment={comment}
            setShowReplies={setShowReplies}
            showReplies={showReplies}
          />
        )}
      </footer>

      <CommentInputDialog
        onOpenChange={setOpenModalComment}
        commentableId={comment.id}
        commentableType={CommentType.COMMENT}
        openModalComment={openModalComment}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        title={
          isReply
            ? `Responder a ${comment.user.name}`
            : `Responder al comentario de ${comment.user.name}`
        }
        uploadedComments={() => {
          onReplyCreated?.(comment.id);
          if (showReplies === comment.id) {
            setRepliesRefreshCounter((currentValue) => currentValue + 1);
          }
          uploadedComments?.();
        }}
      />

      {!isReply && showReplies === comment.id && (
        <CommentRepliesList
          commentId={comment.id}
          onReplyCreated={() => {
            onReplyCreated?.(comment.id);
          }}
          onReplyDeleted={onDeleted}
          refreshCounter={repliesRefreshCounter}
          setShowReplies={setShowReplies}
          showReplies={showReplies}
        />
      )}
    </article>
  );
}

export default CommentItem;
