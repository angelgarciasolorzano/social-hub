import { useCallback, useEffect, useMemo, useState } from "react";

import { ScanLine } from "lucide-react";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { useClipboard } from "@/shared/hooks/useClipboard";

import {
  DEFAULT_TWO_FACTOR_ACTIVATION_STEP,
  type TwoFactorActivationStep,
} from "../../../types/twoFactorActivationStep";
import ChooseMethodStep from "./steps/ChooseMethodStep";
import ManualSetupStep from "./steps/ManualSetupStep";
import TwoFactorSuccessStep from "./steps/TwoFactorSuccessStep";
import VerifyOtpStep from "./steps/VerifyOtpStep";

function GridScanIcon() {
  return (
    <div className="mb-3 rounded-full border border-border bg-card p-0.5 shadow-sm">
      <div className="relative overflow-hidden rounded-full border border-border bg-muted p-2.5">
        <div className="absolute inset-0 grid grid-cols-5 opacity-50">
          {Array.from({ length: 5 }, (_, i) => (
            <div className="border-r border-border last:border-r-0" key={`col-${i + 1}`} />
          ))}
        </div>

        <div className="absolute inset-0 grid grid-rows-5 opacity-50">
          {Array.from({ length: 5 }, (_, i) => (
            <div className="border-b border-border last:border-r-0" key={`row-${i + 1}`} />
          ))}
        </div>

        <ScanLine className="relative z-20 size-6 text-foreground" />
      </div>
    </div>
  );
}

interface TwoFactorSetupDialogProps {
  clearSetupData: () => void;
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  fetchSetupData: () => Promise<void>;
  isOpen: boolean;
  manualSetupKey: string | null;
  onClose: () => void;
  qrCodeSvg: string | null;
  recoveryCodesList: string[];
  requiresConfirmation: boolean;
  twoFactorEnabled: boolean;
}

export default function TwoFactorSetupDialog({
  clearSetupData,
  errors,
  fetchRecoveryCodes,
  fetchSetupData,
  isOpen,
  manualSetupKey,
  onClose,
  qrCodeSvg,
  recoveryCodesList,
  requiresConfirmation,
  twoFactorEnabled,
}: TwoFactorSetupDialogProps) {
  const [step, setStep] = useState<TwoFactorActivationStep>(DEFAULT_TWO_FACTOR_ACTIVATION_STEP);

  const [previousStep, setPreviousStep] = useState<TwoFactorActivationStep | null>(null);

  const [, copy] = useClipboard();

  const modalConfig = useMemo<{ title: string; description: string }>(() => {
    switch (step) {
      case "chooseMethod":
        return {
          description: "Escanea el código QR con tu aplicación autenticadora.",
          title: "Habilitar autenticación de dos factores",
        };

      case "manualSetup":
        return {
          description: "Ingresa esta clave manualmente en tu aplicación autenticadora.",
          title: "No puedes escanear el código QR",
        };

      case "verifyingOTP":
        return {
          description:
            "Ingresa el código de 6 dígitos que muestra tu aplicación para confirmar que funciona correctamente.",
          title: "Verificar código de autenticación",
        };

      case "success":
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
    setStep("success");
  }, [fetchRecoveryCodes]);

  const handleChooseMethodContinue = useCallback((): void => {
    if (requiresConfirmation) {
      goToStep("verifyingOTP");

      return;
    }

    goToSuccess();
  }, [requiresConfirmation, goToSuccess, goToStep]);

  const handleOpenManualSetup = useCallback((): void => {
    goToStep("manualSetup");
  }, [goToStep]);

  const handleManualSetupContinue = useCallback((): void => {
    if (manualSetupKey) {
      void copy(manualSetupKey);
    }
    goToStep("verifyingOTP");
  }, [manualSetupKey, copy, goToStep]);

  const handleOtpBack = useCallback((): void => {
    if (previousStep && previousStep !== "verifyingOTP") {
      setStep(previousStep);
      setPreviousStep(null);

      return;
    }

    setStep("chooseMethod");
    setPreviousStep(null);
  }, [previousStep]);

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

  const renderStep = (): React.ReactNode => {
    switch (step) {
      case "chooseMethod":
        return (
          <ChooseMethodStep
            errors={errors}
            qrCodeSvg={qrCodeSvg}
            onContinue={handleChooseMethodContinue}
            onOpenManualSetup={handleOpenManualSetup}
          />
        );

      case "manualSetup":
        return (
          <ManualSetupStep
            errors={errors}
            manualSetupKey={manualSetupKey}
            onBack={() => {
              setStep("chooseMethod");
              setPreviousStep(null);
            }}
            onContinue={handleManualSetupContinue}
          />
        );

      case "verifyingOTP":
        return <VerifyOtpStep onBack={handleOtpBack} onSuccess={goToSuccess} />;

      case "success":
        return (
          <TwoFactorSuccessStep
            errors={errors}
            fetchRecoveryCodes={fetchRecoveryCodes}
            onClose={handleClose}
            recoveryCodesList={recoveryCodesList}
          />
        );
    }
  };

  return (
    <Dialog onOpenChange={(open) => !open && handleClose()} open={isOpen}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader className="flex items-center justify-center">
          {step !== "success" ? <GridScanIcon /> : null}

          <DialogTitle>{modalConfig.title}</DialogTitle>

          <DialogDescription className="text-center">{modalConfig.description}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col items-center space-y-5">{renderStep()}</div>
      </DialogContent>
    </Dialog>
  );
}
