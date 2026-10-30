import type { JSX } from "react";

import { Info, Loader2 } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

import { useTranslation } from "@/shared/hooks";

import { alertVariants } from "@/shared/lib/styling";

import TwoFactorStepErrorAlert from "./TwoFactorStepErrorAlert";

interface ChooseMethodStepProps {
  errors: string[];
  qrCodeSvg: string | null;
  onContinue: () => void;
  onOpenManualSetup: () => void;
  onRetry: () => void;
}

function ChooseMethodStep(props: ChooseMethodStepProps): JSX.Element {
  const { t } = useTranslation();
  const { errors, qrCodeSvg, onContinue, onOpenManualSetup, onRetry } = props;

  const hasError = errors.length > 0;

  if (hasError) {
    return <TwoFactorStepErrorAlert onRetry={onRetry} />;
  }

  return (
    <div className="flex w-full flex-col items-center space-y-5">
      <div className="mx-auto flex max-w-md overflow-hidden">
        <div className="mx-auto aspect-square w-64 rounded-lg border border-border">
          <div className="z-10 flex h-full w-full items-center justify-center p-5">
            {qrCodeSvg ? (
              <>
                <div
                  aria-label={t("setting/two-factor.choose_method.qr_alt")}
                  dangerouslySetInnerHTML={{
                    __html: qrCodeSvg,
                  }}
                  role="img"
                />
                <span className="sr-only" role="status">
                  {t("setting/two-factor.choose_method.qr_ready")}
                </span>
              </>
            ) : (
              <>
                <Loader2 aria-hidden="true" className="flex size-4 animate-spin" />
                <span className="sr-only" role="status">
                  {t("setting/two-factor.choose_method.qr_loading")}
                </span>
              </>
            )}
          </div>
        </div>
      </div>

      <Button
        type="button"
        className="h-auto cursor-pointer p-0 text-sm font-medium text-violet-700 dark:text-violet-400"
        onClick={onOpenManualSetup}
        variant="link"
      >
        {t("setting/two-factor.choose_method.cannot_scan")}
      </Button>

      <Alert className={alertVariants.info}>
        <Info aria-hidden="true" />
        <AlertTitle>{t("setting/two-factor.choose_method.tip_title")}</AlertTitle>
        <AlertDescription>{t("setting/two-factor.choose_method.tip_description")}</AlertDescription>
      </Alert>

      <div className="flex w-full space-x-5">
        <Button className="w-full cursor-pointer" onClick={onContinue}>
          {t("setting/two-factor.labels.continue")}
        </Button>
      </div>
    </div>
  );
}

export default ChooseMethodStep;
