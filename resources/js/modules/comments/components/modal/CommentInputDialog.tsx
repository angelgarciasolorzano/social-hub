import type { Dispatch, SetStateAction } from "react";

import { useForm } from "@inertiajs/react";

import { IoMdTime } from "react-icons/io";
import { LuMessagesSquare } from "react-icons/lu";

import { Loader2Icon } from "lucide-react";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/ui/avatar";
import { Button } from "@/shared/components/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/ui/dialog";
import { Separator } from "@/shared/components/ui/separator";

import { validImage } from "@/shared/lib";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { CommentableTypeValues } from "../../enums/commentableType";
import type { Comment, CommentFormData } from "../../types/comment";
import CommentForm from "../forms/CommentForm";

interface CommentInputDialogProps {
  comment?: Comment;
  commentableId: number;
  commentableType: CommentableTypeValues;
  uploadedComments?: () => void;
  onOpenChange: (open: boolean) => void;
  openModalComment: boolean;
  setOpenModalComment: Dispatch<SetStateAction<boolean>>;
  title: string;
}

function CommentInputDialog(props: CommentInputDialogProps) {
  const {
    comment,
    commentableId,
    commentableType,
    uploadedComments,
    onOpenChange,
    openModalComment,
    title,
    setOpenModalComment,
  } = props;

  const formComment = useForm<CommentFormData>({
    commentable_id: commentableId,
    commentable_type: commentableType,
    content: "",
  });

  const formId = `comment-form-${commentableType}-${commentableId}`;

  return (
    <Dialog onOpenChange={onOpenChange} open={openModalComment}>
      <DialogContent className="mx-w-[65%] flex min-w-[50%] flex-col">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <LuMessagesSquare className="mb-1 inline-block h-5 w-5 text-gray-600 dark:text-gray-400" />

              {title}
            </div>
          </DialogTitle>

          <DialogDescription>
            {comment ? (
              <>
                Estás respondiendo al comentario de <strong>{comment?.user.name}</strong>. Deja tu
                respuesta y comparte tus pensamientos con el autor del comentario.
              </>
            ) : (
              <>Deja tu comentario y comparte tus pensamientos con el autor de la publicacion.</>
            )}
          </DialogDescription>

          <Separator />
        </DialogHeader>

        <div className="no-scrollbar -mx-4 max-h-[62vh] overflow-y-auto px-4">
          {comment && renderContentDescription({ comment })}

          <CommentForm
            commentableId={commentableId}
            commentableType={commentableType}
            formComment={formComment}
            formId={formId}
            setOpenModalComment={setOpenModalComment}
            uploadedComments={uploadedComments}
          />
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button
              type="button"
              className="cursor-pointer"
              disabled={formComment.processing}
              variant="outline"
            >
              Cancelar
            </Button>
          </DialogClose>

          <Button
            type="submit"
            className="cursor-pointer"
            disabled={formComment.processing}
            form={formId}
          >
            {formComment.processing ? <Loader2Icon className="h-4 w-4 animate-spin" /> : "Publicar"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type RenderContentDescriptionProps = Pick<CommentInputDialogProps, "comment">;

function renderContentDescription({ comment }: RenderContentDescriptionProps) {
  return (
    <div className="mb-4 flex flex-col gap-2 px-2">
      <div className="flex items-center gap-2">
        <Avatar>
          <AvatarImage
            alt={comment?.user.name}
            src={validImage(comment?.user.profilePicture, profilePicturePlaceholder)}
          />
          <AvatarFallback>{comment?.user.name}</AvatarFallback>
        </Avatar>

        <div className="block">
          <span className="text-sm font-bold dark:text-white">{comment?.user.name}</span>

          <time className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <IoMdTime className="h-3 w-3 text-gray-600 dark:text-gray-500" />
            {comment?.createdAt}
          </time>
        </div>
      </div>

      <p className="line-clamp-8 text-sm text-gray-700 dark:text-gray-300">{comment?.content}</p>
    </div>
  );
}

export default CommentInputDialog;
