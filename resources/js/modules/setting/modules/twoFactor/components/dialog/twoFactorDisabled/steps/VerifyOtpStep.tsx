import { useEffect, useRef, useState } from "react";

import { Form } from "@inertiajs/react";

import { REGEXP_ONLY_DIGITS } from "input-otp";
import { Info } from "lucide-react";

import { confirm } from "@/shared/wayfinder/routes/two-factor";

import InputError from "@/shared/components/form/InputError";
import { Alert, AlertDescription } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/shared/components/shadcn/ui/input-otp";

import { OTP_MAX_LENGTH } from "../../../../hooks/useTwoFactorAuth";

interface VerifyOtpStepProps {
  onBack: () => void;
  onSuccess: () => void;
}

function VerifyOtpStep({ onBack, onSuccess }: VerifyOtpStepProps) {
  const [code, setCode] = useState<string>("");
  const pinInputContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      pinInputContainerRef.current?.querySelector("input")?.focus();
    }, 0);

    return () => {
      clearTimeout(timeoutId);
    };
  }, []);

  return (
    <Form {...confirm.form()} onSuccess={onSuccess} resetOnError resetOnSuccess>
      {({
        errors,
        processing,
      }: {
        processing: boolean;
        errors?: { confirmTwoFactorAuthentication?: { code?: string } };
      }) => (
        <div
          className="relative flex w-full flex-col items-center space-y-5"
          ref={pinInputContainerRef}
        >
          <div className="flex w-full flex-col items-center space-y-3 py-2">
            <InputOTP
              id="otp"
              name="code"
              onChange={setCode}
              disabled={processing}
              maxLength={OTP_MAX_LENGTH}
              pattern={REGEXP_ONLY_DIGITS}
            >
              <InputOTPGroup>
                {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                  <InputOTPSlot
                    index={index}
                    key={index}
                    aria-invalid={
                      (errors?.confirmTwoFactorAuthentication?.code?.length ?? 0) > index
                    }
                  />
                ))}
              </InputOTPGroup>
            </InputOTP>
            <InputError message={errors?.confirmTwoFactorAuthentication?.code} />
          </div>

          <Alert className="w-full border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
            <Info />
            <AlertDescription>El código cambia cada 30 segundos en tu aplicación.</AlertDescription>
          </Alert>

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
              disabled={processing || code.length < OTP_MAX_LENGTH}
            >
              Confirmar
            </Button>
          </div>
        </div>
      )}
    </Form>
  );
}

export default VerifyOtpStep;
