import type { JSX, SubmitEvent } from "react";
import { useRef } from "react";

import { useForm } from "@inertiajs/react";

import { ChevronRight, CircleAlert, KeyRound } from "lucide-react";

import { update as updatePassword } from "@/shared/wayfinder/actions/App/Auth/Modules/Password/Controllers/PasswordController";

import { InputError, PasswordInput } from "@/shared/components/form";
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
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { useDialog } from "@/shared/hooks";

import { alertVariants } from "@/shared/lib/styling";

interface PasswordFormData {
  current_password: string;
  password: string;
  password_confirmation: string;
}

function AccountSettingsPasswordDialog(): JSX.Element {
  const { open, setOpen } = useDialog();

  const currentPasswordInput = useRef<HTMLInputElement>(null);
  const newPasswordInput = useRef<HTMLInputElement>(null);
  const passwordConfirmationInput = useRef<HTMLInputElement>(null);

  const { clearErrors, data, errors, processing, reset, setData, submit } =
    useForm<PasswordFormData>({
      current_password: "",
      password: "",
      password_confirmation: "",
    });

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

    submit(updatePassword(), {
      onError: (passwordErrors) => {
        if (passwordErrors["current_password"] !== undefined) {
          currentPasswordInput.current?.focus();

          return;
        }

        if (passwordErrors["password"] !== undefined) {
          newPasswordInput.current?.focus();

          return;
        }

        if (passwordErrors["password_confirmation"] !== undefined) {
          passwordConfirmationInput.current?.focus();
        }
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
          className="w-fit max-w-full justify-between"
          size="sm"
          type="button"
          variant="outline"
        >
          <KeyRound aria-hidden="true" data-icon="inline-start" />
          Cambiar contraseña
          <ChevronRight aria-hidden="true" data-icon="inline-end" />
        </Button>
      </DialogTrigger>

      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <KeyRound aria-hidden="true" className="size-5" />
            Cambiar contraseña
          </DialogTitle>
          <DialogDescription>
            Actualiza tu contraseña para mantener segura tu cuenta.
          </DialogDescription>
        </DialogHeader>

        <form className="grid gap-4" onSubmit={handleSubmit}>
          <Alert className={alertVariants.warning} role="note">
            <CircleAlert aria-hidden="true" />
            <AlertTitle>Se revocará la confianza de tus dispositivos</AlertTitle>
            <AlertDescription>
              Al cambiar tu contraseña, se revocará la confianza de todos los dispositivos asociados
              a tu cuenta. Si tienes activada la autenticación en dos pasos, tendrás que verificarte
              de nuevo la próxima vez que inicies sesión desde esos dispositivos. Las sesiones que
              ya estén abiertas seguirán activas.
            </AlertDescription>
          </Alert>

          <FieldGroup className="gap-4">
            <Field className="gap-2" data-invalid={errors.current_password !== undefined}>
              <FieldLabel htmlFor="account-settings-current-password">Contraseña actual</FieldLabel>
              <PasswordInput
                ref={currentPasswordInput}
                id="account-settings-current-password"
                name="current_password"
                autoComplete="current-password"
                autoFocus
                aria-describedby={
                  errors.current_password !== undefined
                    ? "account-settings-current-password-error"
                    : undefined
                }
                aria-invalid={errors.current_password !== undefined}
                onChange={(event) => {
                  setData("current_password", event.target.value);
                }}
                required
                value={data.current_password}
              />
              <InputError
                aria-live="polite"
                id="account-settings-current-password-error"
                message={errors.current_password}
              />
            </Field>

            <Field className="gap-2" data-invalid={errors.password !== undefined}>
              <FieldLabel htmlFor="account-settings-new-password">Nueva contraseña</FieldLabel>
              <PasswordInput
                ref={newPasswordInput}
                id="account-settings-new-password"
                name="password"
                autoComplete="new-password"
                aria-describedby={
                  errors.password !== undefined ? "account-settings-new-password-error" : undefined
                }
                aria-invalid={errors.password !== undefined}
                onChange={(event) => {
                  setData("password", event.target.value);
                }}
                required
                value={data.password}
              />
              <InputError
                aria-live="polite"
                id="account-settings-new-password-error"
                message={errors.password}
              />
            </Field>

            <Field className="gap-2" data-invalid={errors.password_confirmation !== undefined}>
              <FieldLabel htmlFor="account-settings-password-confirmation">
                Confirmar contraseña
              </FieldLabel>
              <PasswordInput
                ref={passwordConfirmationInput}
                id="account-settings-password-confirmation"
                name="password_confirmation"
                autoComplete="new-password"
                aria-describedby={
                  errors.password_confirmation !== undefined
                    ? "account-settings-password-confirmation-error"
                    : undefined
                }
                aria-invalid={errors.password_confirmation !== undefined}
                onChange={(event) => {
                  setData("password_confirmation", event.target.value);
                }}
                required
                value={data.password_confirmation}
              />
              <InputError
                aria-live="polite"
                id="account-settings-password-confirmation-error"
                message={errors.password_confirmation}
              />
            </Field>
          </FieldGroup>

          <DialogFooter>
            <DialogClose asChild>
              <Button disabled={processing} type="button" variant="outline">
                Cancelar
              </Button>
            </DialogClose>

            <Button disabled={processing} type="submit">
              {processing ? (
                <>
                  <Spinner data-icon="inline-start" />
                  Actualizando...
                </>
              ) : (
                <>
                  <KeyRound data-icon="inline-start" />
                  Guardar contraseña
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default AccountSettingsPasswordDialog;
