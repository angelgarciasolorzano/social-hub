import type { JSX, SubmitEvent } from "react";
import { useState } from "react";

import { useForm } from "@inertiajs/react";

import { LuPencil } from "react-icons/lu";

import { update as updateComment } from "@/shared/wayfinder/actions/App/Modules/Comment/Controllers/CommentController";

import { InputError } from "@/shared/components/form";
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
import { Textarea } from "@/shared/components/shadcn/ui/textarea";

interface CommentEditDialogProps {
  commentId: number;
  initialContent: string;
  onOpenChange: (open: boolean) => void;
  onUpdated: (content: string) => void;
  open: boolean;
}

type CommentEditDialogBodyProps = Omit<CommentEditDialogProps, "open">;

function CommentEditDialog({ open, ...bodyProps }: CommentEditDialogProps): JSX.Element {
  return (
    <Dialog onOpenChange={bodyProps.onOpenChange} open={open}>
      <DialogContent className="flex flex-col">
        <CommentEditDialogBody {...bodyProps} />
      </DialogContent>
    </Dialog>
  );
}

/**
 * Mounted only while the dialog is open, so every opening starts from the current content of the comment.
 */
function CommentEditDialogBody({
  commentId,
  initialContent,
  onOpenChange,
  onUpdated,
}: CommentEditDialogBodyProps): JSX.Element {
  const { clearErrors, data, errors, processing, setData, submit } = useForm<{ content: string }>({
    content: initialContent,
  });

  const [serverError, setServerError] = useState<null | string>(null);

  const formId = `comment-edit-form-${String(commentId)}`;

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();

    clearErrors();
    setServerError(null);

    submit(updateComment(commentId), {
      onHttpException: (response) => {
        setServerError(
          response.status === 429
            ? "Estás editando demasiado rápido. Espera un momento e inténtalo de nuevo."
            : "No se pudo guardar el comentario. Inténtalo de nuevo.",
        );

        return false;
      },
      onNetworkError: () => {
        setServerError("No se pudo conectar con el servidor. Revisa tu conexión.");

        return false;
      },
      onSuccess: () => {
        onUpdated(data.content.trim());

        onOpenChange(false);
      },
      preserveScroll: true,
    });
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle asChild>
          <div className="flex items-center gap-2">
            <LuPencil className="h-5 w-5 text-muted-foreground" />
            Editar comentario
          </div>
        </DialogTitle>

        <DialogDescription>Corrige el texto de tu comentario.</DialogDescription>

        <Separator />
      </DialogHeader>

      <form className="flex flex-col gap-2" id={formId} onSubmit={handleSubmit}>
        <Textarea
          aria-label="Contenido del comentario"
          className="min-h-48 resize-none"
          name="content"
          onChange={(event) => {
            setData("content", event.target.value);
          }}
          value={data.content}
        />

        <InputError className="mt-1" message={errors.content} />

        <InputError className="mt-1" message={serverError ?? undefined} role="alert" />
      </form>

      <DialogFooter>
        <DialogClose asChild>
          <Button className="cursor-pointer" disabled={processing} type="button" variant="outline">
            Cancelar
          </Button>
        </DialogClose>

        <Button className="cursor-pointer" disabled={processing} form={formId} type="submit">
          {processing ? (
            <>
              <Spinner />
              Guardando...
            </>
          ) : (
            "Guardar"
          )}
        </Button>
      </DialogFooter>
    </>
  );
}

export default CommentEditDialog;
