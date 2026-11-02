import type { JSX } from "react";

import { router } from "@inertiajs/react";

import { ArrowRight, CircleAlert, ShieldAlert } from "lucide-react";

import { TrustedDeviceInfoCard } from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceInfoCard";
import { trustedDeviceStatusFilterValue } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
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

interface TrustedDeviceExpiredDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceExpiredDialog({
  existingDevice,
  open,
  onClose,
}: TrustedDeviceExpiredDialogProps): JSX.Element {
  const { t } = useTranslation();
  const handleGoToExpiredList = (): void => {
    onClose();
    router.visit(
      TrustedDeviceIndexController({ query: { status: trustedDeviceStatusFilterValue.inactive } })
        .url,
      {
        preserveScroll: true,
        preserveState: true,
      },
    );
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
              <ShieldAlert aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-device-dialogs.expired.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.expired.description")}
          </DialogDescription>
        </DialogHeader>

        <TrustedDeviceInfoCard
          device={existingDevice}
          expirationLabel={t("setting/trusted-device-dialogs.expired.expired_on")}
        />

        <ExpiredActionsAlert />

        <DialogFooter>
          <Button onClick={onClose} type="button" variant="outline">
            {t("setting/common.actions.close")}
          </Button>
          <Button onClick={handleGoToExpiredList} type="button">
            {t("setting/trusted-device-dialogs.expired.go_to_expired")}
            <ArrowRight />
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ExpiredActionsAlert(): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert className={alertVariants.warning}>
      <CircleAlert />
      <AlertTitle>{t("setting/trusted-device-dialogs.expired.trust_again_title")}</AlertTitle>
      <AlertDescription>
        {t("setting/trusted-device-dialogs.expired.trust_again_description")}
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceExpiredDialog;
