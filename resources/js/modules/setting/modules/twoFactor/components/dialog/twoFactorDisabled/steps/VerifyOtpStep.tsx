import type { JSX } from "react";
import { useEffect, useId, useRef, useState } from "react";

import { Form } from "@inertiajs/react";

import { REGEXP_ONLY_DIGITS } from "input-otp";
import { Info } from "lucide-react";

import { confirm } from "@/shared/wayfinder/routes/two-factor";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Field,
  FieldDescription,
  FieldError,
  FieldLabel,
} from "@/shared/components/shadcn/ui/field";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/shared/components/shadcn/ui/input-otp";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { OTP_MAX_LENGTH } from "../../../../hooks/useTwoFactorAuth";

interface VerifyOtpStepProps {
  onBack: () => void;
  onSuccess: () => void;
}

function VerifyOtpStep({ onBack, onSuccess }: VerifyOtpStepProps): JSX.Element {
  const [code, setCode] = useState<string>("");
  const pinInputContainerRef = useRef<HTMLDivElement>(null);
  const otpInputId = useId();
  const otpInstructionsId = useId();
  const otpErrorId = useId();

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      pinInputContainerRef.current?.querySelector("input")?.focus();
    }, 0);

    return () => {
      clearTimeout(timeoutId);
    };
  }, []);

  return (
    <Form
      {...confirm.form()}
      onError={() => {
        setCode("");
      }}
      onSuccess={onSuccess}
      resetOnError
      resetOnSuccess
    >
      {({
        errors,
        processing,
      }: {
        processing: boolean;
        errors?: { confirmTwoFactorAuthentication?: { code?: string } };
      }) => {
        const otpError = errors?.confirmTwoFactorAuthentication?.code;
        const isOtpInvalid = Boolean(otpError);
        const otpDescribedBy = otpError ? `${otpInstructionsId} ${otpErrorId}` : otpInstructionsId;

        return (
          <div
            className="relative flex w-full flex-col items-center space-y-5"
            ref={pinInputContainerRef}
          >
            <Field className="w-full items-center" data-invalid={isOtpInvalid}>
              <FieldLabel className="justify-center text-center" htmlFor={otpInputId}>
                Código de verificación
              </FieldLabel>

              <InputOTP
                aria-describedby={otpDescribedBy}
                aria-invalid={isOtpInvalid}
                autoComplete="one-time-code"
                containerClassName="justify-center"
                disabled={processing}
                id={otpInputId}
                inputMode="numeric"
                maxLength={OTP_MAX_LENGTH}
                name="code"
                onChange={setCode}
                pattern={REGEXP_ONLY_DIGITS}
                required
                value={code}
              >
                <InputOTPGroup>
                  {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                    <InputOTPSlot aria-invalid={isOtpInvalid} index={index} key={index} />
                  ))}
                </InputOTPGroup>
              </InputOTP>

              <FieldError id={otpErrorId}>{otpError}</FieldError>

              <FieldDescription
                className="flex items-center gap-2 text-center"
                id={otpInstructionsId}
              >
                <Info aria-hidden="true" className="size-4 shrink-0" />
                <span>El código cambia cada 30 segundos en tu aplicación.</span>
              </FieldDescription>
            </Field>

            <div className="flex w-full space-x-5">
              <Button
                type="button"
                className="flex-1 cursor-pointer"
                onClick={onBack}
                disabled={processing}
                variant="outline"
              >
                Volver
              </Button>

              <Button
                type="submit"
                className="flex-1 cursor-pointer"
                aria-busy={processing}
                aria-live="polite"
                disabled={processing || code.length < OTP_MAX_LENGTH}
              >
                {processing ? (
                  <>
                    <Spinner aria-hidden="true" data-icon="inline-start" />
                    Verificando…
                  </>
                ) : (
                  "Confirmar"
                )}
              </Button>
            </div>
          </div>
        );
      }}
    </Form>
  );
}

export default VerifyOtpStep;
