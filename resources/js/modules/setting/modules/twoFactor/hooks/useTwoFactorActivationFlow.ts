import { useCallback, useEffect, useMemo, useState } from "react";

import {
  DEFAULT_TWO_FACTOR_ACTIVATION_STEP,
  type TwoFactorActivationStep,
  twoFactorActivationStepKey,
} from "../types/twoFactorActivationStep";

interface UseTwoFactorActivationFlowParams {
  clearSetupData: () => void;
  fetchRecoveryCodes: () => Promise<void>;
  fetchSetupData: () => Promise<void>;
  isOpen: boolean;
  qrCodeSvg: string | null;
  onClose: () => void;
  requiresConfirmation: boolean;
  twoFactorEnabled: boolean;
}

export interface UseTwoFactorActivationFlowReturn {
  step: TwoFactorActivationStep;
  modalConfig: { description: string; title: string };
  handleChooseMethodContinue: () => void;
  handleClose: () => void;
  handleManualSetupBack: () => void;
  handleManualSetupContinue: () => void;
  handleOpenManualSetup: () => void;
  handleOtpBack: () => void;
  handleOtpSuccess: () => void;
}

export function useTwoFactorActivationFlow(
  params: UseTwoFactorActivationFlowParams,
): UseTwoFactorActivationFlowReturn {
  const {
    clearSetupData,
    fetchRecoveryCodes,
    fetchSetupData,
    isOpen,
    onClose,
    qrCodeSvg,
    requiresConfirmation,
    twoFactorEnabled,
  } = params;

  const [step, setStep] = useState<TwoFactorActivationStep>(DEFAULT_TWO_FACTOR_ACTIVATION_STEP);

  const [previousStep, setPreviousStep] = useState<TwoFactorActivationStep | null>(null);

  const modalConfig = useMemo<{ description: string; title: string }>(() => {
    switch (step) {
      case twoFactorActivationStepKey.chooseMethod:
        return {
          description: "Escanea el código QR con tu aplicación autenticadora.",
          title: "Habilitar autenticación de dos factores",
        };

      case twoFactorActivationStepKey.manualSetup:
        return {
          description: "Ingresa esta clave manualmente en tu aplicación autenticadora.",
          title: "No puedes escanear el código QR",
        };

      case twoFactorActivationStepKey.verifyingOTP:
        return {
          description:
            "Ingresa el código de 6 dígitos que muestra tu aplicación para confirmar que funciona correctamente.",
          title: "Verificar código de autenticación",
        };

      case twoFactorActivationStepKey.success:
        return {
          description: "Guarda estos códigos de respaldo en un lugar seguro.",
          title: "¡2FA activado correctamente!",
        };
    }
  }, [step]);

  const resetModalState = useCallback((): void => {
    setStep(DEFAULT_TWO_FACTOR_ACTIVATION_STEP);
    setPreviousStep(null);

    if (twoFactorEnabled) {
      clearSetupData();
    }
  }, [twoFactorEnabled, clearSetupData]);

  const goToStep = useCallback(
    (next: TwoFactorActivationStep): void => {
      setPreviousStep(step);
      setStep(next);
    },
    [step],
  );

  const goToSuccess = useCallback((): void => {
    void fetchRecoveryCodes();
    setStep(twoFactorActivationStepKey.success);
  }, [fetchRecoveryCodes]);

  const handleChooseMethodContinue = useCallback((): void => {
    if (requiresConfirmation) {
      goToStep(twoFactorActivationStepKey.verifyingOTP);

      return;
    }

    goToSuccess();
  }, [requiresConfirmation, goToStep, goToSuccess]);

  const handleOpenManualSetup = useCallback((): void => {
    goToStep(twoFactorActivationStepKey.manualSetup);
  }, [goToStep]);

  const handleManualSetupBack = useCallback((): void => {
    setStep(twoFactorActivationStepKey.chooseMethod);
    setPreviousStep(null);
  }, []);

  const handleManualSetupContinue = useCallback((): void => {
    goToStep(twoFactorActivationStepKey.verifyingOTP);
  }, [goToStep]);

  const handleOtpBack = useCallback((): void => {
    if (previousStep && previousStep !== twoFactorActivationStepKey.verifyingOTP) {
      setStep(previousStep);
      setPreviousStep(null);

      return;
    }

    setStep(twoFactorActivationStepKey.chooseMethod);
    setPreviousStep(null);
  }, [previousStep]);

  const handleOtpSuccess = goToSuccess;

  const handleClose = useCallback((): void => {
    resetModalState();
    onClose();
  }, [resetModalState, onClose]);

  useEffect(() => {
    if (!isOpen) {
      const timeoutId = setTimeout(() => {
        resetModalState();
      }, 0);

      return () => {
        clearTimeout(timeoutId);
      };
    }

    if (!qrCodeSvg) {
      void fetchSetupData();
    }

    return undefined;
  }, [isOpen, qrCodeSvg, fetchSetupData, resetModalState]);

  return {
    handleChooseMethodContinue,
    handleClose,
    handleManualSetupBack,
    handleManualSetupContinue,
    handleOpenManualSetup,
    handleOtpBack,
    handleOtpSuccess,
    modalConfig,
    step,
  };
}
