import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

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

import type { CommentableType } from "../../enums/commentableType";
import CommentForm from "../forms/CommentForm";

interface CommentInputDialogProps {
  commentableId: number;
  commentableType: CommentableType;
  onCommentPosted: (() => void) | undefined;
  onOpenChange: (open: boolean) => void;
  openModalComment: boolean;
  setOpenModalComment: Dispatch<SetStateAction<boolean>>;
  title: string;
}

function CommentInputDialog(props: CommentInputDialogProps) {
  const {
    commentableId,
    commentableType,
    onCommentPosted,
    onOpenChange,
    openModalComment,
    title,
    setOpenModalComment,
  } = props;

  const formId = `comment-form-${commentableType}-${commentableId}`;

  const [processing, setProcessing] = useState<boolean>(false);

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
          onCommentPosted={onCommentPosted}
          commentableId={commentableId}
          commentableType={commentableType}
          formId={formId}
          setOpenModalComment={setOpenModalComment}
          setProcessing={setProcessing}
        />

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" className="cursor-pointer" variant="outline">
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" className="cursor-pointer" disabled={processing} form={formId}>
            {processing ? <Loader2Icon className="h-4 w-4 animate-spin" /> : "Publicar"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default CommentInputDialog;
