import type { Dispatch, SetStateAction, SubmitEvent } from "react";

import { useForm } from "@inertiajs/react";

import { AlertTriangleIcon, RefreshCcwDot } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/User/Controllers/TwoFactorRecoveryCodeController";

import { InputError, LabelForm, PasswordInput } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/ui/alert";
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
import { Spinner } from "@/shared/components/ui/spinner";

interface RegenerateCodesDialogProps {
  isOpen: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  fetchRecoveryCodes: () => Promise<void>;
}

type RegenerateCodesFormData = {
  password: string;
};

function RegenerateCodesDialog({
  isOpen,
  setOpen,
  fetchRecoveryCodes,
}: RegenerateCodesDialogProps) {
  const { setData, errors, submit, processing, reset, data } = useForm<RegenerateCodesFormData>({
    password: "",
  });

  const handleSubmit = (e: SubmitEvent<HTMLFormElement>): void => {
    e.preventDefault();

    submit(store(), {
      onSuccess: () => {
        setOpen(false);
        reset();
        fetchRecoveryCodes();
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
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <RefreshCcwDot className="h-5 w-5 text-muted-foreground" />
              Regenerar códigos de respaldo
            </div>
          </DialogTitle>
          <DialogDescription>
            ¿Estás seguro de que quieres regenerar tus códigos de respaldo? Esta acción no se puede
            deshacer.
          </DialogDescription>
        </DialogHeader>

        <Alert className="my-2 max-w-md border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-500">
          <AlertTriangleIcon />
          <AlertTitle>Los códigos actuales dejarán de funcionar.</AlertTitle>
          <AlertDescription>
            Una vez que generes nuevos códigos, los anteriores no pódran usarse.
          </AlertDescription>
        </Alert>

        <form id="regenerate-codes-form" onSubmit={handleSubmit} className="mt-2 grid gap-2">
          <LabelForm error={errors.password} htmlFor="regenerate-codes-password">
            Para continuar, escribe tu contraseña
          </LabelForm>

          <PasswordInput
            id="regenerate-codes-password"
            name="password"
            autoComplete="current-password"
            onChange={(e) => setData("password", e.target.value)}
            aria-invalid={!!errors.password}
            required
            autoFocus
            placeholder="Contraseña"
            value={data.password}
          />

          <InputError message={errors.password} />
        </form>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" form="regenerate-codes-form" disabled={processing}>
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
