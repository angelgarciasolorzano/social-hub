import type { ReactNode } from "react";

import type { LucideIcon } from "lucide-react";
import { ArrowLeft, CircleCheck, Hash, ScanLine, Smartphone } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { cn } from "@/shared/lib";

import { useTwoFactorActivationFlow } from "../../../hooks/useTwoFactorActivationFlow";
import {
  type TwoFactorActivationStep,
  twoFactorActivationStepKey,
} from "../../../types/twoFactorActivationStep";
import ChooseMethodStep from "./steps/ChooseMethodStep";
import ManualSetupStep from "./steps/ManualSetupStep";
import TwoFactorSuccessStep from "./steps/TwoFactorSuccessStep";
import VerifyOtpStep from "./steps/VerifyOtpStep";

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
  const {
    handleChooseMethodContinue,
    handleClose,
    handleManualSetupBack,
    handleManualSetupContinue,
    handleOpenManualSetup,
    handleOtpBack,
    handleOtpSuccess,
    modalConfig,
    step,
  } = useTwoFactorActivationFlow({
    clearSetupData,
    fetchRecoveryCodes,
    fetchSetupData,
    isOpen,
    onClose,
    qrCodeSvg,
    requiresConfirmation,
    twoFactorEnabled,
  });

  const handleRetry = (): void => {
    void fetchSetupData();
  };

  const renderStep = (): ReactNode => {
    switch (step) {
      case twoFactorActivationStepKey.chooseMethod:
        return (
          <ChooseMethodStep
            errors={errors}
            qrCodeSvg={qrCodeSvg}
            onContinue={handleChooseMethodContinue}
            onOpenManualSetup={handleOpenManualSetup}
            onRetry={handleRetry}
          />
        );

      case twoFactorActivationStepKey.manualSetup:
        return (
          <ManualSetupStep
            errors={errors}
            manualSetupKey={manualSetupKey}
            onContinue={handleManualSetupContinue}
            onRetry={handleRetry}
          />
        );

      case twoFactorActivationStepKey.verifyingOTP:
        return <VerifyOtpStep onBack={handleOtpBack} onSuccess={handleOtpSuccess} />;

      case twoFactorActivationStepKey.success:
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
        {step === twoFactorActivationStepKey.manualSetup ? (
          <Button
            type="button"
            aria-label="Volver"
            variant="outline"
            size="icon-sm"
            className="absolute top-3 left-3 z-10 inline-flex cursor-pointer items-center justify-center rounded-md"
            onClick={handleManualSetupBack}
          >
            <ArrowLeft className="size-5" aria-hidden="true" />
          </Button>
        ) : null}

        <DialogHeader className="flex items-center justify-center">
          <DialogHeaderIcon step={step} />

          <DialogTitle>{modalConfig.title}</DialogTitle>

          <DialogDescription className="text-center">{modalConfig.description}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col items-center space-y-5">{renderStep()}</div>
      </DialogContent>
    </Dialog>
  );
}

interface DialogHeaderIconProps {
  step: TwoFactorActivationStep;
}

function DialogHeaderIcon({ step }: DialogHeaderIconProps) {
  switch (step) {
    case twoFactorActivationStepKey.chooseMethod:
      return <DialogHeaderIconWrapper icon={ScanLine} iconClassName="text-foreground" />;
    case twoFactorActivationStepKey.manualSetup:
      return (
        <DialogHeaderIconWrapper
          icon={Smartphone}
          iconClassName="text-violet-700 dark:text-violet-400"
        />
      );
    case twoFactorActivationStepKey.verifyingOTP:
      return <DialogHeaderIconWrapper icon={Hash} iconClassName="text-foreground" />;
    case twoFactorActivationStepKey.success:
      return (
        <DialogHeaderIconWrapper
          icon={CircleCheck}
          iconClassName="text-green-600 dark:text-green-400"
          innerClassName="bg-green-50 dark:bg-green-500/10"
        />
      );
  }
}

interface DialogHeaderIconWrapperProps {
  icon: LucideIcon;
  iconClassName: string;
  innerClassName?: string;
}

function DialogHeaderIconWrapper({
  icon: Icon,
  iconClassName,
  innerClassName,
}: DialogHeaderIconWrapperProps) {
  return (
    <div className="mb-3 rounded-full border border-border bg-card p-0.5 shadow-sm">
      <div className={cn("rounded-full border border-border p-2.5", innerClassName ?? "bg-muted")}>
        <Icon className={cn("size-6", iconClassName)} />
      </div>
    </div>
  );
}
