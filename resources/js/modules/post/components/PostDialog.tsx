import type { Dispatch, SetStateAction } from "react";

import { useForm } from "@inertiajs/react";

import { MdOutlinePostAdd } from "react-icons/md";

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
import { Separator } from "@/shared/components/ui/separator";

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
      <DialogContent className="max-w-[50%] min-w-[40%]">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <MdOutlinePostAdd className="h-5 w-5 text-gray-600 dark:text-gray-400" />
              Crear publicación
            </div>
          </DialogTitle>

          <DialogDescription>Comparte tus ideas y pensamientos con el mundo.</DialogDescription>

          <Separator />
        </DialogHeader>

        <div className="-mx-4 max-h-[70vh] overflow-y-auto px-4">
          <PostForm form={form} setOpen={setOpen} />
        </div>

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
