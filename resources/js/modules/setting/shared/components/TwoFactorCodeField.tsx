import { type JSX, useState } from "react";

import TwoFactorOtpField from "@/modules/setting/shared/components/TwoFactorOtpField";

import { InputError, LabelForm } from "@/shared/components/form";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Input } from "@/shared/components/shadcn/ui/input";

import { useTranslation } from "@/shared/hooks";

const RECOVERY_CODE_MAX_LENGTH = 21;

interface TwoFactorCodeFieldProps {
  id: string;
  name: string;
  value: string;
  error: string | undefined;
  disabled: boolean;
  onChange: (value: string) => void;
  onModeChange?: () => void;
}

/**
 * Asks for a verification code: the six digits of the authenticator app, or
 * a single-use recovery code that the user can switch to with a link.
 */
function TwoFactorCodeField({
  id,
  name,
  value,
  error,
  disabled,
  onChange,
  onModeChange,
}: TwoFactorCodeFieldProps): JSX.Element {
  const { t } = useTranslation();
  const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);

  const handleToggleRecoveryInput = (): void => {
    setShowRecoveryInput(!showRecoveryInput);
    onChange("");
    onModeChange?.();
  };

  return (
    <div className="flex flex-col gap-4">
      {showRecoveryInput ? (
        <RecoveryCodeInput
          id={`${id}-recovery`}
          name={name}
          value={value}
          error={error}
          disabled={disabled}
          onChange={onChange}
        />
      ) : (
        <TwoFactorOtpField
          id={`${id}-otp`}
          name={name}
          label={t("setting/common.two_factor_code.otp_label")}
          value={value}
          error={error}
          disabled={disabled}
          onChange={onChange}
        />
      )}

      <Button
        type="button"
        variant="link"
        className="self-center"
        disabled={disabled}
        onClick={handleToggleRecoveryInput}
      >
        {showRecoveryInput
          ? t("setting/common.two_factor_code.use_authenticator_code")
          : t("setting/common.two_factor_code.use_recovery_code")}
      </Button>
    </div>
  );
}

interface RecoveryCodeInputProps {
  id: string;
  name: string;
  value: string;
  error: string | undefined;
  disabled: boolean;
  onChange: (value: string) => void;
}

function RecoveryCodeInput({
  id,
  name,
  value,
  error,
  disabled,
  onChange,
}: RecoveryCodeInputProps): JSX.Element {
  const { t } = useTranslation();
  const errorId = `${id}-error`;

  return (
    <div className="flex flex-col gap-2">
      <LabelForm error={error} htmlFor={id}>
        {t("setting/common.two_factor_code.recovery_label")}
      </LabelForm>

      <Input
        id={id}
        name={name}
        type="text"
        required
        autoComplete="off"
        spellCheck={false}
        placeholder="xxxxxxxxxx-xxxxxxxxxx"
        maxLength={RECOVERY_CODE_MAX_LENGTH}
        disabled={disabled}
        value={value}
        aria-describedby={error ? errorId : undefined}
        aria-invalid={error ? "true" : "false"}
        onChange={(event) => {
          onChange(event.target.value.trim());
        }}
      />

      <p className="text-xs text-muted-foreground">
        {t("setting/common.two_factor_code.recovery_single_use")}
      </p>

      {error && <InputError aria-live="polite" id={errorId} message={error} />}
    </div>
  );
}

export default TwoFactorCodeField;
