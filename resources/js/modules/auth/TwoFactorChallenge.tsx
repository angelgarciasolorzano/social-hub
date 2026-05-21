import { useMemo, useState } from "react";

import { Form, Head, setLayoutProps } from "@inertiajs/react";

import { REGEXP_ONLY_DIGITS } from "input-otp";

import { store } from "@/shared/wayfinder/routes/two-factor/login";

import { InputError } from "@/shared/components/form";
import { Button } from "@/shared/components/ui/button";
import { Input } from "@/shared/components/ui/input";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/shared/components/ui/input-otp";

import { OTP_MAX_LENGTH } from "@/shared/hooks";

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
        title: "Recovery code",
        description:
          "Please confirm access to your account by entering one of your emergency recovery codes.",
        toggleText: "login using an authentication code",
      };
    }

    return {
      title: "Authentication code",
      description: "Enter the authentication code provided by your authenticator application.",
      toggleText: "login using a recovery code",
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
    </>
  );
}
