import { useMemo, useState } from "react";

import { Form, Head, setLayoutProps } from "@inertiajs/react";

import { REGEXP_ONLY_DIGITS } from "input-otp";

import { store } from "@/shared/wayfinder/routes/two-factor/login";

import { InputError } from "@/shared/components/form";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Input } from "@/shared/components/shadcn/ui/input";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/shared/components/shadcn/ui/input-otp";

const OTP_MAX_LENGTH = 6;

export default function TwoFactorChallenge() {
  const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
  const [code, setCode] = useState<string>("");

  const authConfigContent = useMemo<{
    title: string;
    description: string;
    toggleText: string;
  }>(() => {
    if (showRecoveryInput) {
      return {
        title: "Código de recuperación",
        description:
          "Por favor, confirma el acceso a tu cuenta ingresando uno de tus códigos de recuperación de emergencia.",
        toggleText: "iniciar sesión usando un código de autenticación",
      };
    }

    return {
      title: "Código de autenticación",
      description:
        "Ingresa el código de autenticación proporcionado por tu aplicación de autenticación.",
      toggleText: "iniciar sesión usando un código de recuperación",
    };
  }, [showRecoveryInput]);

  setLayoutProps({
    title: authConfigContent.title,
    description: authConfigContent.description,
  });

  const toggleRecoveryMode = (clearErrors: () => void): void => {
    setShowRecoveryInput(!showRecoveryInput);
    clearErrors();
    setCode("");
  };

  return (
    <>
      <Head title="Two-factor authentication" />

      <div className="space-y-6">
        <Form
          {...store.form()}
          className="space-y-4"
          resetOnError
          resetOnSuccess={!showRecoveryInput}
        >
          {({ errors, processing, clearErrors }) => (
            <>
              {showRecoveryInput ? (
                <>
                  <Input
                    name="recovery_code"
                    type="text"
                    autoFocus={showRecoveryInput}
                    placeholder="Enter recovery code"
                    required
                  />

                  <InputError message={errors["recovery_code"]} />
                </>
              ) : (
                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                  <div className="flex w-full items-center justify-center">
                    <InputOTP
                      name="code"
                      onChange={(value) => setCode(value)}
                      autoFocus
                      disabled={processing}
                      maxLength={OTP_MAX_LENGTH}
                      pattern={REGEXP_ONLY_DIGITS}
                      value={code}
                    >
                      <InputOTPGroup>
                        {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                          <InputOTPSlot index={index} key={index} />
                        ))}
                      </InputOTPGroup>
                    </InputOTP>
                  </div>
                  <InputError message={errors["code"]} />
                </div>
              )}

              <Button type="submit" className="w-full" disabled={processing}>
                Continue
              </Button>

              <div className="text-center text-sm text-muted-foreground">
                <span>o puedes </span>

                <button
                  type="button"
                  className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                  onClick={() => toggleRecoveryMode(clearErrors)}
                >
                  {authConfigContent.toggleText}
                </button>
              </div>
            </>
          )}
        </Form>
      </div>
    </>
  );
}
