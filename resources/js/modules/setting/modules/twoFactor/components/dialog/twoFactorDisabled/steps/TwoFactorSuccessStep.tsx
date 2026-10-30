import type { JSX } from "react";
import { useCallback } from "react";

import { usePage } from "@inertiajs/react";

import { AlertCircleIcon, ArrowDown, Info } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

import { downloadRecoveryCodes } from "../../../../utils/downloadRecoveryCodes";

interface TwoFactorSuccessStepProps {
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  hasFinishedRecoveryCodesRequest: boolean;
  isLoadingRecoveryCodes: boolean;
  onClose: () => void;
  recoveryCodesList: string[];
}

function TwoFactorSuccessStep(props: TwoFactorSuccessStepProps): JSX.Element {
  const {
    errors,
    fetchRecoveryCodes,
    hasFinishedRecoveryCodesRequest,
    isLoadingRecoveryCodes,
    onClose,
    recoveryCodesList,
  } = props;

  const isRecoveryCodesLoading = isLoadingRecoveryCodes || !hasFinishedRecoveryCodesRequest;
  const hasRecoveryCodes = recoveryCodesList.length > 0;

  if (errors.length > 0) {
    return (
      <RecoveryCodesLoadError
        fetchRecoveryCodes={fetchRecoveryCodes}
        isLoadingRecoveryCodes={isLoadingRecoveryCodes}
        onClose={onClose}
      />
    );
  }

  return (
    <>
      <RecoveryCodesList
        isRecoveryCodesLoading={isRecoveryCodesLoading}
        recoveryCodesList={recoveryCodesList}
      />

      {hasRecoveryCodes ? <RecoveryCodesNotice /> : null}

      <RecoveryCodesActions onClose={onClose} recoveryCodesList={recoveryCodesList} />
    </>
  );
}

interface RecoveryCodesLoadErrorProps {
  fetchRecoveryCodes: () => Promise<void>;
  isLoadingRecoveryCodes: boolean;
  onClose: () => void;
}

function RecoveryCodesLoadError(props: RecoveryCodesLoadErrorProps): JSX.Element {
  const { t } = useTranslation();
  const { fetchRecoveryCodes, isLoadingRecoveryCodes, onClose } = props;

  const handleRetry = useCallback((): void => {
    void fetchRecoveryCodes();
  }, [fetchRecoveryCodes]);

  return (
    <div className="flex w-full flex-col gap-4">
      <Alert className={cn(alertVariants.destructive, "w-full")}>
        <AlertCircleIcon aria-hidden="true" />
        <AlertTitle>{t("setting/two-factor.success_step.load_failed_title")}</AlertTitle>

        <AlertDescription>
          {t("setting/two-factor.success_step.load_failed_description")}
        </AlertDescription>
      </Alert>

      <div className="flex w-full gap-5">
        <Button
          type="button"
          variant="outline"
          className="flex-1 cursor-pointer"
          onClick={handleRetry}
          disabled={isLoadingRecoveryCodes}
        >
          {isLoadingRecoveryCodes
            ? t("setting/common.actions.retrying")
            : t("setting/common.actions.retry")}
        </Button>

        <Button type="button" className="flex-1 cursor-pointer" onClick={onClose}>
          {t("setting/two-factor.labels.understood")}
        </Button>
      </div>
    </div>
  );
}

interface RecoveryCodesListProps {
  isRecoveryCodesLoading: boolean;
  recoveryCodesList: string[];
}

function RecoveryCodesList(props: RecoveryCodesListProps): JSX.Element {
  const { t } = useTranslation();
  const { isRecoveryCodesLoading, recoveryCodesList } = props;

  return (
    <>
      <ul
        aria-busy={isRecoveryCodesLoading}
        aria-label={t("setting/two-factor.labels.backup_codes")}
        className="grid w-full grid-cols-2 gap-2"
      >
        {recoveryCodesList.length > 0 ? (
          recoveryCodesList.map((code) => (
            <li
              key={code}
              className="rounded-md border border-border bg-muted/40 px-3 py-2 text-center font-mono text-sm text-foreground"
            >
              {code}
            </li>
          ))
        ) : isRecoveryCodesLoading ? (
          Array.from({ length: 8 }, (_, index) => (
            <li aria-hidden="true" key={`skeleton-${index}`}>
              <Skeleton className="h-9 border border-border" />
            </li>
          ))
        ) : (
          <li className="col-span-2 text-center text-sm text-muted-foreground">
            {t("setting/two-factor.labels.no_backup_codes")}
          </li>
        )}
      </ul>

      <span className="sr-only" role="status">
        {recoveryCodesList.length > 0
          ? t("setting/two-factor.success_step.ready")
          : isRecoveryCodesLoading
            ? t("setting/two-factor.labels.loading_backup_codes")
            : t("setting/two-factor.labels.no_backup_codes")}
      </span>
    </>
  );
}

function RecoveryCodesNotice(): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert className={cn(alertVariants.warning, "w-full")}>
      <Info aria-hidden="true" />
      <AlertTitle>{t("setting/two-factor.success_step.important")}</AlertTitle>
      <AlertDescription>{t("setting/two-factor.success_step.single_use")}</AlertDescription>
    </Alert>
  );
}

interface RecoveryCodesActionsProps {
  onClose: () => void;
  recoveryCodesList: string[];
}

function RecoveryCodesActions(props: RecoveryCodesActionsProps): JSX.Element {
  const { onClose, recoveryCodesList } = props;
  const { locale, t } = useTranslation();
  const { email: accountEmail } = usePage<SharedData>().props.auth.user;

  const handleDownload = useCallback((): void => {
    downloadRecoveryCodes(recoveryCodesList, { accountEmail, locale, t });
  }, [recoveryCodesList, accountEmail, locale, t]);

  return (
    <div className="flex w-full gap-5">
      <Button
        type="button"
        variant="outline"
        className="flex-1 cursor-pointer"
        onClick={handleDownload}
        disabled={!recoveryCodesList.length}
      >
        <ArrowDown aria-hidden="true" data-icon="inline-start" />
        {t("setting/two-factor.labels.download_txt")}
      </Button>

      <Button type="button" className="flex-1 cursor-pointer" onClick={onClose}>
        {t("setting/two-factor.labels.understood")}
      </Button>
    </div>
  );
}

export default TwoFactorSuccessStep;
