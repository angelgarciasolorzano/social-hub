import type { JSX, SubmitEvent } from "react";
import { useRef } from "react";

import { useForm } from "@inertiajs/react";

import { AlertTriangle, Trash2 } from "lucide-react";

import { destroy as destroyUser } from "@/shared/wayfinder/actions/App/User/Controllers/UserController";

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

import { useAppearance, useDialog, useTranslation } from "@/shared/hooks";

import { alertVariants, buttonVariants, iconColorVariants } from "@/shared/lib/styling";

interface AccountSettingsDeleteDialogProps {
  userId: number;
}

interface DeleteAccountFormData {
  password: string;
}

function AccountSettingsDeleteDialog({ userId }: AccountSettingsDeleteDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { open, setOpen } = useDialog();
  const { resolvedAppearance } = useAppearance();

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
          {t("setting/account-settings.delete_dialog.trigger")}
        </Button>
      </DialogTrigger>

      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Trash2 aria-hidden="true" className={`size-5 ${iconColorVariants.red.iconFgClass}`} />
            {t("setting/account-settings.delete_dialog.title")}
          </DialogTitle>
          <DialogDescription>
            {t("setting/account-settings.delete_dialog.description")}
          </DialogDescription>
        </DialogHeader>

        <Alert className={alertVariants.destructive} role="note">
          <AlertTriangle aria-hidden="true" />
          <AlertTitle>{t("setting/account-settings.delete_dialog.warning_title")}</AlertTitle>
          <AlertDescription>
            {t("setting/account-settings.delete_dialog.warning_description")}
          </AlertDescription>
        </Alert>

        <form className="grid gap-4" id="delete-account-form" onSubmit={handleSubmit}>
          <FieldGroup className="gap-4">
            <Field className="gap-2" data-invalid={errors.password !== undefined}>
              <FieldLabel htmlFor="delete-account-password">
                {t("setting/account-settings.delete_dialog.current_password")}
              </FieldLabel>
              <PasswordInput
                ref={passwordInput}
                id="delete-account-password"
                name="password"
                autoComplete="current-password"
                autoFocus
                aria-describedby={
                  errors.password !== undefined ? "delete-account-password-error" : undefined
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
                id="delete-account-password-error"
                message={errors.password}
              />
            </Field>
          </FieldGroup>
        </form>

        <DialogFooter>
          <DialogClose asChild>
            <Button disabled={processing} type="button" variant="outline">
              {t("setting/common.actions.cancel")}
            </Button>
          </DialogClose>

          <Button
            disabled={processing}
            form="delete-account-form"
            type="submit"
            variant={resolvedAppearance === "light" ? "destructive" : undefined}
            className={buttonVariants.destructive}
          >
            {processing ? (
              <>
                <Spinner data-icon="inline-start" />
                {t("setting/account-settings.delete_dialog.submitting")}
              </>
            ) : (
              <>
                <Trash2 data-icon="inline-start" />
                {t("setting/account-settings.delete_dialog.submit")}
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default AccountSettingsDeleteDialog;
