import { type Dispatch, type SetStateAction, useState } from "react";

import { IoMdTime } from "react-icons/io";

import dayjs from "dayjs";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";

import { useModal } from "@/shared/hooks/useModal";

import { validImage } from "@/shared/lib";
import { cn } from "@/shared/lib/utils";

import { profilePicturePlaceholder } from "@/shared/assets";

import { CommentableType } from "../enums/commentableType";
import type { Comment, CommentContextPreview } from "../types/comment";
import CommentInputDialog from "./modal/CommentInputDialog";
import RepliesList from "./RepliesList";

interface CommentItemProps {
  comment: Comment;
  isReply?: boolean;
  onReplyCreated?: (commentId: number) => void;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
  uploadedComments?: () => void;
}

function CommentItem(props: CommentItemProps) {
  const {
    comment,
    isReply = false,
    onReplyCreated,
    setShowReplies,
    showReplies,
    uploadedComments,
  } = props;

  const { open: openModalComment, setOpen: setOpenModalComment } = useModal();

  const [repliesRefreshCounter, setRepliesRefreshCounter] = useState<number>(0);

  const createdAt = dayjs(comment.createdAt).fromNow();

  const previewContext: CommentContextPreview = {
    authorName: comment.user.name,
    authorProfilePicture: comment.user.profilePicture,
    content: comment.content,
    kind: CommentableType.COMMENT,
    createdAt: createdAt + " - " + dayjs(comment.createdAt).format("D [de] MMMM [del] YYYY"),
  };

  return (
    <article className={cn("space-y-1", isReply && "ml-2")}>
      <div
        className={cn(
          !isReply && "rounded-md border bg-gray-50/50 dark:border-[#343434] dark:bg-[#1D1D1D]/30",
          "flex flex-col gap-2 p-2",
        )}
      >
        <header className="flex items-center gap-2">
          <Avatar>
            <AvatarImage
              alt="Foto de perfil"
              src={validImage(comment.user.profilePicture, profilePicturePlaceholder)}
            />

            <AvatarFallback>Foto de perfil</AvatarFallback>
          </Avatar>

          <div className="block">
            <p className="text-sm font-bold dark:text-white">{comment.user.name}</p>

            <time className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
              <IoMdTime className="h-3 w-3 text-gray-600 dark:text-gray-500" />
              {createdAt}
            </time>
          </div>
        </header>

        <section>
          <p className="text-sm text-gray-700 dark:text-gray-300">{comment.content}</p>
        </section>
      </div>

      <footer className="flex items-center gap-4 text-xs font-medium text-gray-600 dark:text-gray-400">
        <button className="cursor-pointer hover:text-red-600 dark:hover:text-red-500">
          Me gusta
        </button>

        <button
          type="button"
          className="cursor-pointer transition-colors hover:text-black/80 dark:hover:text-white/80"
          onClick={() => setOpenModalComment(true)}
        >
          Responder
        </button>

        {comment.repliesInfo.hasReplies && (
          <button
            type="button"
            className="cursor-pointer transition-colors hover:text-blue-600 dark:hover:text-blue-500"
            onClick={() => setShowReplies(showReplies === comment.id ? null : comment.id)}
          >
            {showReplies === comment.id
              ? "Ocultar respuestas"
              : `Mostrar ${comment.repliesInfo.repliesCount} respuestas `}
          </button>
        )}
      </footer>

      <CommentInputDialog
        onOpenChange={setOpenModalComment}
        commentableId={comment.id}
        commentableType={CommentableType.COMMENT}
        openModalComment={openModalComment}
        previewContext={previewContext}
        setOpenModalComment={setOpenModalComment}
        title={`Responder al comentario de ${comment.user.name}`}
        uploadedComments={() => {
          onReplyCreated?.(comment.id);
          if (showReplies === comment.id) {
            setRepliesRefreshCounter((currentValue) => currentValue + 1);
          }
          uploadedComments?.();
        }}
      />

      {showReplies === comment.id && (
        <RepliesList
          commentId={comment.id}
          refreshCounter={repliesRefreshCounter}
          setShowReplies={setShowReplies}
          showReplies={showReplies}
        />
      )}
    </article>
  );
}

export default CommentItem;
