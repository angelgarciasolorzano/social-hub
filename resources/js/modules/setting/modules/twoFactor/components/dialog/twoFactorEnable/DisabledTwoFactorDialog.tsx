import type { Dispatch, SetStateAction, SubmitEvent } from "react";

import { useForm } from "@inertiajs/react";

import { REGEXP_ONLY_DIGITS } from "input-otp";
import { ShieldBan, ShieldQuestionMark } from "lucide-react";

import { destroy } from "@/shared/wayfinder/routes/setting/security/two-factor-authentication";

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
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
} from "@/shared/components/ui/input-otp";
import { Spinner } from "@/shared/components/ui/spinner";

import { OTP_MAX_LENGTH } from "../../../hooks/useTwoFactorAuth";

type DisableTwoFactorFormData = {
  password: string;
  code: string;
};

interface DisableTwoFactorDialogProps {
  isOpen: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function DisabledTwoFactorDialog({ isOpen, setOpen }: DisableTwoFactorDialogProps) {
  const { data, setData, processing, errors, reset, submit } = useForm<DisableTwoFactorFormData>({
    password: "",
    code: "",
  });

  const handleSubmit = (e: SubmitEvent<HTMLFormElement>): void => {
    e.preventDefault();

    submit(destroy(), {
      onSuccess: () => {
        setOpen(false);
        reset();
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
              <ShieldBan className="h-5 w-5 text-muted-foreground" />
              Desactivar Two-Factor Authentication
            </div>
          </DialogTitle>
          <DialogDescription>
            Por seguridad, necesitamos verificar tu identidad antes de desactivar la autenticación
            de dos factores.
          </DialogDescription>
        </DialogHeader>

        <Alert className="border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
          <ShieldQuestionMark />

          <AlertTitle className="line-clamp-4">
            ¿Estás seguro de que quieres desactivar la autenticación de dos factores?
          </AlertTitle>

          <AlertDescription>
            Desactivar 2FA hará tu cuenta más vulnerable. Solo deberías hacerlo si tienes un motivo
            específico.
          </AlertDescription>
        </Alert>

        <form id="regenerate-codes-form" className="mt-2 grid gap-6" onSubmit={handleSubmit}>
          <div className="flex flex-col gap-2">
            <LabelForm error={errors.password} htmlFor="regenerate-codes-password">
              Contraseña
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
          </div>

          <div className="flex flex-col items-center justify-center space-y-3 text-center">
            <LabelForm error={errors.code} htmlFor="code-otp">
              Código de tu aplicación autenticadora
            </LabelForm>

            <div className="flex w-full items-center justify-center">
              <InputOTP
                name="code-otp"
                required
                onChange={(e) => setData("code", e)}
                disabled={processing}
                maxLength={OTP_MAX_LENGTH}
                pattern={REGEXP_ONLY_DIGITS}
                value={data.code}
              >
                <InputOTPGroup className="*:data-[slot=input-otp-slot]:h-12 *:data-[slot=input-otp-slot]:w-12 *:data-[slot=input-otp-slot]:text-lg">
                  {Array.from({ length: OTP_MAX_LENGTH / 2 }, (_, index) => (
                    <InputOTPSlot index={index} key={index} />
                  ))}
                </InputOTPGroup>

                <InputOTPSeparator className="mx-2" />

                <InputOTPGroup className="*:data-[slot=input-otp-slot]:h-12 *:data-[slot=input-otp-slot]:w-12 *:data-[slot=input-otp-slot]:text-lg">
                  {Array.from({ length: OTP_MAX_LENGTH / 2 }, (_, index) => (
                    <InputOTPSlot index={index + OTP_MAX_LENGTH / 2} key={index} />
                  ))}
                </InputOTPGroup>
              </InputOTP>
            </div>
            <InputError message={errors.code} />
          </div>
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
                Desactivando 2FA...
              </>
            ) : (
              "Desactivar 2FA"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default DisabledTwoFactorDialog;
