import { type JSX, type SubmitEvent, useId, useRef } from "react";

import { useForm } from "@inertiajs/react";

import { AlertTriangleIcon, RefreshCcwDot } from "lucide-react";

import { storeRecoveryCodes } from "@/shared/wayfinder/routes/setting/security/two-factor-authentication";

import { InputError, LabelForm, PasswordInput } from "@/shared/components/form";
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
} from "@/shared/components/shadcn/ui/dialog";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

interface RegenerateCodesDialogProps {
  isOpen: boolean;
  setOpen: (open: boolean) => void;
  fetchRecoveryCodes: () => Promise<void>;
}

interface RegenerateCodesFormData {
  password: string;
}

function RegenerateCodesDialog({
  isOpen,
  setOpen,
  fetchRecoveryCodes,
}: RegenerateCodesDialogProps): JSX.Element {
  const idPrefix = useId();
  const formId = `${idPrefix}-regenerate-codes-form`;
  const passwordInputId = `${idPrefix}-password`;
  const passwordErrorId = `${idPrefix}-password-error`;
  const returnFocusRef = useRef<HTMLElement | null>(null);

  const { setData, errors, submit, processing, reset, data } = useForm<RegenerateCodesFormData>({
    password: "",
  });

  const handleSubmit = (e: SubmitEvent<HTMLFormElement>): void => {
    e.preventDefault();

    submit(storeRecoveryCodes(), {
      onSuccess: () => {
        setOpen(false);
        reset();
        void fetchRecoveryCodes();
      },
      preserveState: true,
    });
  };

  return (
    <Dialog
      open={isOpen}
      onOpenChange={(nextOpen) => {
        if (processing && !nextOpen) {
          return;
        }

        setOpen(nextOpen);
      }}
    >
      <DialogContent
        onOpenAutoFocus={() => {
          const activeElement = document.activeElement;

          returnFocusRef.current =
            activeElement instanceof HTMLElement && activeElement !== document.body
              ? activeElement
              : null;
        }}
        onCloseAutoFocus={(event) => {
          const focusTarget = returnFocusRef.current;

          if (focusTarget?.isConnected) {
            event.preventDefault();
            focusTarget.focus();
          }

          returnFocusRef.current = null;
        }}
      >
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2">
              <RefreshCcwDot className="h-5 w-5 text-muted-foreground" />
              Regenerar códigos de respaldo
            </h2>
          </DialogTitle>
          <DialogDescription>
            ¿Estás seguro de que quieres regenerar tus códigos de respaldo? Esta acción no se puede
            deshacer.
          </DialogDescription>
        </DialogHeader>

        <Alert className={cn(alertVariants.destructive, "my-2")}>
          <AlertTriangleIcon />
          <AlertTitle className="font-normal">
            Los códigos actuales dejarán de funcionar.
          </AlertTitle>
          <AlertDescription>
            Una vez que generes nuevos códigos, los anteriores no pódran usarse.
          </AlertDescription>
        </Alert>

        <form id={formId} onSubmit={handleSubmit} className="mt-2 grid gap-2">
          <LabelForm error={errors.password} htmlFor={passwordInputId}>
            Para continuar, escribe tu contraseña
          </LabelForm>

          <PasswordInput
            id={passwordInputId}
            name="password"
            autoComplete="current-password"
            onChange={(e) => {
              setData("password", e.target.value);
            }}
            aria-invalid={!!errors.password}
            aria-describedby={errors.password ? passwordErrorId : undefined}
            required
            placeholder="Contraseña"
            value={data.password}
          />

          <InputError id={passwordErrorId} message={errors.password} />
        </form>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" form={formId} disabled={processing}>
            {processing ? (
              <>
                <Spinner />
                Regenerando...
              </>
            ) : (
              "Regenerar códigos"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default RegenerateCodesDialog;
