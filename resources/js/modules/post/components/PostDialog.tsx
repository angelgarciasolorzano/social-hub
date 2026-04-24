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

import type { PostFormData } from "../types/post";
import PostForm from "./forms/PostForm";

interface PostDialogProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function PostDialog({ open, setOpen }: PostDialogProps) {
  const form = useForm<PostFormData>({
    content: "",
  });

  return (
    <Dialog
      onOpenChange={(nextOpen) => {
        if (form.processing && !nextOpen) {
          return;
        }

        setOpen(nextOpen);
      }}
      open={open}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Crear publicación</DialogTitle>

          <DialogDescription>Comparte tus ideas y pensamientos con el mundo.</DialogDescription>
        </DialogHeader>

        <PostForm form={form} setOpen={setOpen} />

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" disabled={form.processing} variant="outline">
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" disabled={form.processing} form="post-form">
            {form.processing ? <Loader2Icon className="h-4 w-4 animate-spin" /> : "Publicar"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default PostDialog;
