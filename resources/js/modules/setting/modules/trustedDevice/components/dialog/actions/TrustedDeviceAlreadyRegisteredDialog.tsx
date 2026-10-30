import type { JSX } from "react";

import { router } from "@inertiajs/react";

import { ArrowRight, CircleAlert, ShieldCheck } from "lucide-react";

import { TrustedDeviceInfoCard } from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceInfoCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

import TrustedDeviceIndexController from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { useTranslation } from "@/shared/hooks";

import { alertVariants } from "@/shared/lib/styling";

interface TrustedDeviceAlreadyRegisteredDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
  showListLink?: boolean;
}

function TrustedDeviceAlreadyRegisteredDialog({
  existingDevice,
  open,
  onClose,
  showListLink = true,
}: TrustedDeviceAlreadyRegisteredDialogProps): JSX.Element {
  const { t } = useTranslation();
  const handleGoToList = (): void => {
    onClose();
    router.visit(TrustedDeviceIndexController().url, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          onClose();
        }
      }}
    >
      <DialogContent className="max-w-3xl min-w-2xl">
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <ShieldCheck aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-device-dialogs.already_registered.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.already_registered.description")}
          </DialogDescription>
        </DialogHeader>

        <TrustedDeviceInfoCard
          device={existingDevice}
          expirationLabel={t("setting/trusted-devices.info_card.expires_on")}
        />

        {showListLink && <AlreadyRegisteredActionsAlert onGoToList={handleGoToList} />}

        <DialogFooter>
          <Button onClick={onClose} type="button">
            {t("setting/common.actions.close")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface AlreadyRegisteredActionsAlertProps {
  onGoToList: () => void;
}

function AlreadyRegisteredActionsAlert({
  onGoToList,
}: AlreadyRegisteredActionsAlertProps): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert className={alertVariants.info}>
      <CircleAlert />
      <AlertTitle>
        {t("setting/trusted-device-dialogs.already_registered.changes_title")}
      </AlertTitle>
      <AlertDescription className="gap-2 text-sm font-normal">
        {t("setting/trusted-device-dialogs.already_registered.changes_description")}
        <Button onClick={onGoToList} size="sm" variant="outline">
          {t("setting/trusted-device-dialogs.already_registered.go_to_devices")}
          <ArrowRight />
        </Button>
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceAlreadyRegisteredDialog;
