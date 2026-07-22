import { useCallback, useState } from "react";

import { useHttp } from "@inertiajs/react";

import { enable, qrCode, recoveryCodes, secretKey } from "@/shared/wayfinder/routes/two-factor";

interface UseTwoFactorAuthReturn {
  qrCodeSvg: string | null;
  manualSetupKey: string | null;
  recoveryCodesList: string[];
  hasSetupData: boolean;
  errors: string[];
  clearErrors: () => void;
  clearSetupData: () => void;
  clearTwoFactorAuthData: () => void;
  enableTwoFactorAuthentication: () => Promise<void>;
  fetchQrCode: () => Promise<void>;
  fetchSetupKey: () => Promise<void>;
  fetchSetupData: () => Promise<void>;
  fetchRecoveryCodes: () => Promise<void>;
}

export const OTP_MAX_LENGTH = 6;

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
  const { submit } = useHttp();

  const [qrCodeSvg, setQrCodeSvg] = useState<string | null>(null);
  const [manualSetupKey, setManualSetupKey] = useState<string | null>(null);
  const [recoveryCodesList, setRecoveryCodesList] = useState<string[]>([]);
  const [errors, setErrors] = useState<string[]>([]);

  const hasSetupData = qrCodeSvg !== null && manualSetupKey !== null;

  const clearErrors = useCallback((): void => {
    setErrors([]);
  }, []);

  const clearSetupData = useCallback((): void => {
    setManualSetupKey(null);
    setQrCodeSvg(null);
    setErrors([]);
  }, []);

  const clearTwoFactorAuthData = useCallback((): void => {
    setManualSetupKey(null);
    setQrCodeSvg(null);
    setErrors([]);
    setRecoveryCodesList([]);
  }, []);

  const enableTwoFactorAuthentication = useCallback(async (): Promise<void> => {
    try {
      await submit(enable());
    } catch {
      setErrors((prev) => [...prev, "Failed to enable two factor authentication"]);
    }
  }, [submit]);

  const fetchQrCode = useCallback(async (): Promise<void> => {
    try {
      const { svg } = (await submit(qrCode())) as {
        svg: string;
        url: string;
      };

      setQrCodeSvg(svg);
    } catch {
      setErrors((prev) => [...prev, "Failed to fetch QR code"]);
      setQrCodeSvg(null);
    }
  }, [submit]);

  const fetchSetupKey = useCallback(async (): Promise<void> => {
    try {
      const { secretKey: key } = (await submit(secretKey())) as {
        secretKey: string;
      };

      setManualSetupKey(key);
    } catch {
      setErrors((prev) => [...prev, "Failed to fetch a setup key"]);
      setManualSetupKey(null);
    }
  }, [submit]);

  const fetchRecoveryCodes = useCallback(async (): Promise<void> => {
    try {
      setErrors([]);
      const codes = (await submit(recoveryCodes())) as string[];
      setRecoveryCodesList(codes);
    } catch {
      setErrors((prev) => [...prev, "Failed to fetch recovery codes"]);
      setRecoveryCodesList([]);
    }
  }, [submit]);

  const fetchSetupData = useCallback(async (): Promise<void> => {
    try {
      setErrors([]);
      await enableTwoFactorAuthentication();
      await Promise.all([fetchQrCode(), fetchSetupKey()]);
    } catch {
      setQrCodeSvg(null);
      setManualSetupKey(null);
    }
  }, [enableTwoFactorAuthentication, fetchQrCode, fetchSetupKey]);

  return {
    qrCodeSvg,
    manualSetupKey,
    recoveryCodesList,
    hasSetupData,
    errors,
    clearErrors,
    clearSetupData,
    clearTwoFactorAuthData,
    enableTwoFactorAuthentication,
    fetchQrCode,
    fetchSetupKey,
    fetchSetupData,
    fetchRecoveryCodes,
  };
};
