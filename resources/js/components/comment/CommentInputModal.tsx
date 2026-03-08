import { Dispatch, SetStateAction, useState } from "react";

import { Loader2Icon } from "lucide-react";

import { CommentableType } from "@/enums";

import { Button } from "../ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "../ui/dialog";
import CommentForm from "./CommentForm";

interface CommentInputModalProps {
  commentableId: number;
  commentableType: CommentableType;
  onCommentPosted?: () => void;
  onOpenChange: (open: boolean) => void;
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  title: string;
}

function CommentInputModal(props: CommentInputModalProps) {
  const { commentableId, commentableType, onCommentPosted, onOpenChange, open, title, setOpen } =
    props;

  const formId = `comment-form-${commentableType}-${commentableId}`;

  const [processing, setProcessing] = useState<boolean>(false);

  return (
    <Dialog onOpenChange={onOpenChange} open={open}>
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
          setOpen={setOpen}
          setProcessing={setProcessing}
          formId={formId}
        />

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" className="cursor-pointer" variant="outline">
              Cancelar
            </Button>
          </DialogClose>

          <Button
            type="submit"
            className="cursor-pointer"
            disabled={processing}
            form={formId}
          >
            {processing ? <Loader2Icon className="h-4 w-4 animate-spin" /> : "Publicar"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default CommentInputModal;
