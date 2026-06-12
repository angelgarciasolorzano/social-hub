import type { Dispatch, SetStateAction } from "react";

import { useForm } from "@inertiajs/react";

import { IoMdTime } from "react-icons/io";
import { LuMessagesSquare } from "react-icons/lu";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import { Separator } from "@/shared/components/shadcn/ui/separator";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { validImage } from "@/shared/lib";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { CommentableTypeValues } from "../../enums/commentableType";
import type { CommentContextPreview, CommentFormData } from "../../types/comment";
import CommentForm from "../forms/CommentForm";

interface CommentInputDialogProps {
  previewContext?: CommentContextPreview;
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
    previewContext,
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
              <LuMessagesSquare className="h-5 w-5 text-gray-600 dark:text-gray-400" />

              {title}
            </div>
          </DialogTitle>

          <DialogDescription>
            {previewContext ? (
              <>
                {previewContext.kind === "comment"
                  ? "Estás respondiendo al comentario de "
                  : "Estás comentando la publicación de "}
                <strong>{previewContext.authorName}</strong>.
              </>
            ) : (
              <>Deja tu comentario y comparte tus pensamientos.</>
            )}
          </DialogDescription>

          <Separator />
        </DialogHeader>

        <div className="-mx-4 max-h-[62vh] overflow-y-auto px-4">
          {previewContext && <CommentPreviewContent previewContext={previewContext} />}

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
            {formComment.processing ? (
              <>
                <Spinner />
                Publicando...
              </>
            ) : (
              "Publicar"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type CommentPreviewContentProps = Pick<CommentInputDialogProps, "previewContext">;

function CommentPreviewContent({ previewContext }: CommentPreviewContentProps) {
  return (
    <div className="mb-4 flex flex-col gap-2 px-2">
      <div className="flex items-center gap-2">
        <Avatar>
          <AvatarImage
            alt={previewContext?.authorName}
            src={validImage(previewContext?.authorProfilePicture, profilePicturePlaceholder)}
          />
          <AvatarFallback>{previewContext?.authorName}</AvatarFallback>
        </Avatar>

        <div className="block">
          <span className="text-sm font-bold dark:text-white">{previewContext?.authorName}</span>

          <time className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <IoMdTime className="h-3 w-3 text-gray-600 dark:text-gray-500" />
            {previewContext?.createdAt}
          </time>
        </div>
      </div>

      <p className="line-clamp-8 text-sm text-gray-700 dark:text-gray-300">
        {previewContext?.content}
      </p>
    </div>
  );
}

export default CommentInputDialog;
