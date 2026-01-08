import { useEffect, useMemo, useRef, useState } from "react";

import { Form, Head } from "@inertiajs/react";

import { OTP_MAX_LENGTH } from "@/features/settings/hooks/useTwoFactorAuth";
import { REGEXP_ONLY_DIGITS } from "input-otp";

import { store } from "@/routes/two-factor/login";

import InputError from "@/components/form/InputError";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/components/ui/input-otp";

import AuthCardLayout from "./layouts/AuthCardLayout";

export default function TwoFactorChallenge() {
  const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
  const [code, setCode] = useState<string>("");

  const pinInputContainerRef = useRef<HTMLDivElement>(null);

  const authConfigContent = useMemo<{
    title: string;
    description: string;
    toggleText: string;
  }>(() => {
    if (showRecoveryInput) {
      return {
        description:
          "Please confirm access to your account by entering one of your emergency recovery codes.",
        title: "Recovery Code",
        toggleText: "login using an authentication code",
      };
    }

    return {
      description: "Enter the authentication code provided by your authenticator application.",
      title: "Authentication Code",
      toggleText: "login using a recovery code",
    };
  }, [showRecoveryInput]);

  const toggleRecoveryMode = (clearErrors: () => void): void => {
    setShowRecoveryInput(!showRecoveryInput);
    clearErrors();
    setCode("");
  };

  useEffect(() => {
    if (!showRecoveryInput) {
      setTimeout(() => {
        pinInputContainerRef.current?.querySelector("input")?.focus();
      });
    }
  }, [showRecoveryInput]);

  return (
    <AuthCardLayout description={authConfigContent.description} title={authConfigContent.title}>
      <Head title="Two-Factor Authentication" />

      <div className="space-y-6">
        <Form
          {...store.form()}
          className="space-y-4"
          resetOnError
          resetOnSuccess={!showRecoveryInput}
        >
          {({ clearErrors, errors, processing }) => (
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
                  <InputError message={errors.recovery_code} />
                </>
              ) : (
                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                  <div
                    className="flex w-full items-center justify-center"
                    ref={pinInputContainerRef}
                  >
                    <InputOTP
                      name="code"
                      onChange={(value) => setCode(value)}
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
                  <InputError message={errors.code} />
                </div>
              )}

              <Button type="submit" className="w-full" disabled={processing}>
                Continue
              </Button>

              <div className="text-center text-sm text-muted-foreground">
                <span>or you can </span>
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
    </AuthCardLayout>
  );
}
