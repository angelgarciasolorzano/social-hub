import type { Dispatch, SetStateAction } from "react";

import { useForm } from "@inertiajs/react";

import { Loader2Icon } from "lucide-react";

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

import type { CommentableTypeValues } from "../../enums/commentableType";
import type { CommentFormData } from "../../types/comment";
import CommentForm from "../forms/CommentForm";

interface CommentInputDialogProps {
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
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>

          <DialogDescription>
            Deja tu comentario y comparte tus pensamientos con el autor de la publicación.
          </DialogDescription>
        </DialogHeader>

        <CommentForm
          commentableId={commentableId}
          commentableType={commentableType}
          formComment={formComment}
          formId={formId}
          setOpenModalComment={setOpenModalComment}
          uploadedComments={uploadedComments}
        />

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

export default CommentInputDialog;
