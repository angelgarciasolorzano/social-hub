import type { JSX, SubmitEvent } from "react";
import { useState } from "react";

import { useForm } from "@inertiajs/react";

import { LuTrash2 } from "react-icons/lu";

import { destroy as destroyComment } from "@/shared/wayfinder/actions/App/Modules/Comment/Controllers/CommentController";

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

interface CommentDeleteDialogProps {
  commentId: number;
  onDeleted: () => void;
  onOpenChange: (open: boolean) => void;
  open: boolean;
}

function CommentDeleteDialog({ open, ...bodyProps }: CommentDeleteDialogProps): JSX.Element {
  return (
    <Dialog onOpenChange={bodyProps.onOpenChange} open={open}>
      <DialogContent className="flex flex-col">
        <CommentDeleteDialogBody {...bodyProps} />
      </DialogContent>
    </Dialog>
  );
}

type CommentDeleteDialogBodyProps = Omit<CommentDeleteDialogProps, "open">;

/**
 * Mounted only while the dialog is open, so a failed attempt never leaves its error for the next opening.
 */
function CommentDeleteDialogBody({
  commentId,
  onDeleted,
  onOpenChange,
}: CommentDeleteDialogBodyProps): JSX.Element {
  const { processing, submit } = useForm({});

  const [serverError, setServerError] = useState<null | string>(null);

  const formId = `comment-delete-form-${String(commentId)}`;

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();

    if (processing) return;

    setServerError(null);

    submit(destroyComment(commentId), {
      onHttpException: (response) => {
        setServerError(
          response.status === 429
            ? "Estás eliminando demasiado rápido. Espera un momento e inténtalo de nuevo."
            : "No se pudo eliminar el comentario. Inténtalo de nuevo.",
        );

        return false;
      },
      onNetworkError: () => {
        setServerError("No se pudo conectar con el servidor. Revisa tu conexión.");

        return false;
      },
      onSuccess: () => {
        onDeleted();

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
            <LuTrash2 className="h-5 w-5 text-destructive" />
            Eliminar comentario
          </div>
        </DialogTitle>

        <DialogDescription>
          ¿Seguro que quieres eliminar este comentario? Esta acción no se puede deshacer.
        </DialogDescription>

        <Separator />
      </DialogHeader>

      <form id={formId} onSubmit={handleSubmit}>
        <InputError message={serverError ?? undefined} role="alert" />
      </form>

      <DialogFooter>
        <DialogClose asChild>
          <Button className="cursor-pointer" disabled={processing} type="button" variant="outline">
            Cancelar
          </Button>
        </DialogClose>

        <Button
          className="cursor-pointer"
          disabled={processing}
          form={formId}
          type="submit"
          variant="destructive"
        >
          {processing ? (
            <>
              <Spinner />
              Eliminando...
            </>
          ) : (
            "Eliminar"
          )}
        </Button>
      </DialogFooter>
    </>
  );
}

export default CommentDeleteDialog;
