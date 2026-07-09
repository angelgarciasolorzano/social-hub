export const twoFactorActivationStepKey = {
  chooseMethod: "chooseMethod",
  manualSetup: "manualSetup",
  verifyingOTP: "verifyingOTP",
  success: "success",
} as const;

export type TwoFactorActivationStep =
  (typeof twoFactorActivationStepKey)[keyof typeof twoFactorActivationStepKey];

export const DEFAULT_TWO_FACTOR_ACTIVATION_STEP: TwoFactorActivationStep =
  twoFactorActivationStepKey.chooseMethod;
