import type { JSX, SubmitEvent } from "react";
import { useRef } from "react";

import { useForm } from "@inertiajs/react";

import { AlertTriangle, Trash2 } from "lucide-react";

import { destroy as destroyUser } from "@/shared/wayfinder/actions/App/User/Controllers/UserController";

import { InputError } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/shared/components/shadcn/ui/dialog";
import { Field, FieldGroup, FieldLabel } from "@/shared/components/shadcn/ui/field";
import { Input } from "@/shared/components/shadcn/ui/input";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { useDialog } from "@/shared/hooks";

import { alertVariants, buttonVariants } from "@/shared/lib/styling";

interface AccountSettingsDeleteDialogProps {
  userId: number;
}

interface DeleteAccountFormData {
  password: string;
}

function AccountSettingsDeleteDialog({ userId }: AccountSettingsDeleteDialogProps): JSX.Element {
  const { open, setOpen } = useDialog();

  const passwordInput = useRef<HTMLInputElement>(null);

  const { clearErrors, data, errors, processing, reset, setData, submit } =
    useForm<DeleteAccountFormData>({ password: "" });

  const handleOpenChange = (nextOpen: boolean): void => {
    if (processing && !nextOpen) {
      return;
    }

    setOpen(nextOpen);

    if (!nextOpen) {
      reset();
      clearErrors();
    }
  };

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();

    submit(destroyUser({ user: userId }), {
      onError: () => {
        passwordInput.current?.focus();
      },
      onSuccess: () => {
        setOpen(false);
        reset();
      },
      preserveScroll: true,
    });
  };

  return (
    <Dialog onOpenChange={handleOpenChange} open={open}>
      <DialogTrigger asChild>
        <Button
          className="w-fit max-w-full text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/30 dark:hover:text-red-300"
          type="button"
          variant="outline"
        >
          <Trash2 aria-hidden="true" data-icon="inline-start" />
          Eliminar mi cuenta
        </Button>
      </DialogTrigger>

      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Trash2 aria-hidden="true" className="size-5 text-destructive" />
            Eliminar mi cuenta
          </DialogTitle>
          <DialogDescription>
            Para continuar, confirma la eliminación con tu contraseña actual.
          </DialogDescription>
        </DialogHeader>

        <Alert className={alertVariants.destructive} role="note">
          <AlertTriangle aria-hidden="true" />
          <AlertTitle>Esta acción es permanente</AlertTitle>
          <AlertDescription>
            Se eliminarán los datos de tu cuenta y se cerrará tu sesión.
          </AlertDescription>
        </Alert>

        <form className="grid gap-4" id="delete-account-form" onSubmit={handleSubmit}>
          <FieldGroup className="gap-4">
            <Field className="gap-2" data-invalid={errors.password !== undefined}>
              <FieldLabel htmlFor="delete-account-password">Contraseña actual</FieldLabel>
              <Input
                ref={passwordInput}
                id="delete-account-password"
                name="password"
                type="password"
                autoComplete="current-password"
                autoFocus
                aria-describedby={errors.password ? "delete-account-password-error" : undefined}
                aria-invalid={errors.password !== undefined}
                onChange={(event) => {
                  setData("password", event.target.value);
                }}
                required
                value={data.password}
              />
              <InputError
                aria-live="polite"
                id="delete-account-password-error"
                message={errors.password}
              />
            </Field>
          </FieldGroup>
        </form>

        <DialogFooter>
          <DialogClose asChild>
            <Button disabled={processing} type="button" variant="outline">
              Cancelar
            </Button>
          </DialogClose>

          <Button
            disabled={processing}
            form="delete-account-form"
            type="submit"
            className={buttonVariants.destructive}
          >
            {processing ? (
              <>
                <Spinner data-icon="inline-start" />
                Eliminando...
              </>
            ) : (
              <>
                <Trash2 data-icon="inline-start" />
                Eliminar cuenta
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default AccountSettingsDeleteDialog;
