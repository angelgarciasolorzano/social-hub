import { Dispatch, SetStateAction } from "react";

import { IoMdTime } from "react-icons/io";

import profilePlaceholder from "@/assets/profile-placeholder.png";
import { CommentableType } from "@/enums";
import { cn } from "@/lib/utils";

import { validImage } from "@/utils";

import { Comment } from "@/types";

import { Avatar, AvatarFallback, AvatarImage } from "../ui/avatar";
import CommentForm from "./CommentForm";

interface CommentItemProps {
  comment: Comment;
  isReply?: boolean;
  replyTo: number | null;
  setReplyTo: Dispatch<SetStateAction<number | null>>;
  setShowReplies: Dispatch<SetStateAction<number | null>>;
  showReplies: number | null;
}

function CommentItem(props: CommentItemProps) {
  const { comment, isReply = false, replyTo, setReplyTo, setShowReplies, showReplies } = props;

  return (
    <article className={cn("space-y-1", isReply && "ml-2")}>
      <div
        className={cn(
          !isReply && "rounded-md border bg-gray-50/50 dark:border-gray-500/40 dark:bg-gray-700/20",
          "space-y-1 p-2",
        )}
      >
        <header className="flex items-center gap-2">
          <Avatar>
            <AvatarImage
              alt="Foto de perfil"
              src={validImage(comment.user.profilePicture, profilePlaceholder)}
            />

            <AvatarFallback>Foto de perfil</AvatarFallback>
          </Avatar>

          <div className="block">
            <p className="text-sm font-bold dark:text-white">{comment.user.name}</p>

            <time className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
              <IoMdTime className="h-3 w-3 text-gray-600 dark:text-gray-500" />
              {comment.created_at}
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
          onClick={() => setReplyTo(replyTo === comment.id ? null : comment.id)}
        >
          Responder
        </button>

        {comment.replies && comment.replies.length > 0 && (
          <button
            type="button"
            className="dark:hover:text-blue-500transition-colors cursor-pointer hover:text-blue-600"
            onClick={() => setShowReplies(showReplies === comment.id ? null : comment.id)}
          >
            {showReplies === comment.id
              ? "Ocultar respuestas"
              : `Mostrar ${comment.replies.length} respuestas`}
          </button>
        )}
      </footer>

      {replyTo === comment.id && (
        <CommentForm
          commentableId={comment.id}
          commentableType={CommentableType.COMMENT}
          setReplyTo={setReplyTo}
        />
      )}

      {showReplies === comment.id && (
        <>
          {comment.replies &&
            comment.replies.map((replie) => (
              <div className="mt-4" key={replie.id}>
                <CommentItem
                  comment={replie}
                  isReply
                  replyTo={replyTo}
                  setReplyTo={setReplyTo}
                  setShowReplies={setShowReplies}
                  showReplies={showReplies}
                />
              </div>
            ))}
        </>
      )}
    </article>
  );
}

export default CommentItem;
