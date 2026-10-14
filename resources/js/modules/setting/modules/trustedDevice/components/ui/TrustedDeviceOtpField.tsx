import type { JSX } from "react";

import { REGEXP_ONLY_DIGITS } from "input-otp";

import { OTP_MAX_LENGTH } from "@/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth";

import { InputError, LabelForm } from "@/shared/components/form";
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
} from "@/shared/components/shadcn/ui/input-otp";

interface TrustedDeviceOtpFieldProps {
  id: string;
  label: string;
  value: string;
  error: string | undefined;
  disabled: boolean;
  onChange: (value: string) => void;
}

const otpSlotClassName =
  "*:data-[slot=input-otp-slot]:h-12 *:data-[slot=input-otp-slot]:w-12 *:data-[slot=input-otp-slot]:text-lg";

function TrustedDeviceOtpField({
  id,
  label,
  value,
  error,
  disabled,
  onChange,
}: TrustedDeviceOtpFieldProps): JSX.Element {
  const errorId = `${id}-error`;
  const halfLength = OTP_MAX_LENGTH / 2;

  return (
    <div className="flex flex-col items-center justify-center gap-2 text-center">
      <LabelForm error={error} htmlFor={id}>
        {label}
      </LabelForm>

      <div className="flex w-full items-center justify-center">
        <InputOTP
          id={id}
          name="otp_code"
          required
          onChange={onChange}
          disabled={disabled}
          maxLength={OTP_MAX_LENGTH}
          pattern={REGEXP_ONLY_DIGITS}
          value={value}
          aria-describedby={error ? errorId : undefined}
          aria-invalid={error ? "true" : "false"}
        >
          <InputOTPGroup className={otpSlotClassName}>
            {Array.from({ length: halfLength }, (_, index) => (
              <InputOTPSlot index={index} key={index} />
            ))}
          </InputOTPGroup>

          <InputOTPSeparator className="mx-2" />

          <InputOTPGroup className={otpSlotClassName}>
            {Array.from({ length: halfLength }, (_, index) => (
              <InputOTPSlot index={index + halfLength} key={index} />
            ))}
          </InputOTPGroup>
        </InputOTP>
      </div>

      {error && <InputError aria-live="polite" id={errorId} message={error} />}
    </div>
  );
}

export default TrustedDeviceOtpField;
