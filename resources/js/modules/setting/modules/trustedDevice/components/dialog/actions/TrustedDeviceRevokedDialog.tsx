import type { JSX } from "react";

import { router } from "@inertiajs/react";

import { ArrowRight, CircleAlert, ShieldOff } from "lucide-react";

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
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { useTranslation } from "@/shared/hooks";

import { alertVariants } from "@/shared/lib/styling";

interface TrustedDeviceRevokedDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceRevokedDialog({
  existingDevice,
  open,
  onClose,
}: TrustedDeviceRevokedDialogProps): JSX.Element {
  const { t } = useTranslation();
  const handleGoToRevokedList = (): void => {
    onClose();
    router.visit(
      TrustedDeviceIndexController({ query: { status: trustedDeviceStatusFilterValue.revoked } })
        .url,
      {
        preserveScroll: true,
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
              <ShieldOff aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-device-dialogs.revoked.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.revoked.description")}
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <TrustedDeviceInfoCard
          device={existingDevice}
          expirationLabel={t("setting/trusted-devices.info_card.expires_on")}
        />

        <RevokedActionsAlert />

        <DialogFooter>
          <Button onClick={onClose} type="button" variant="outline">
            {t("setting/common.actions.close")}
          </Button>
          <Button onClick={handleGoToRevokedList} type="button">
            {t("setting/trusted-device-dialogs.revoked.go_to_revoked")}
            <ArrowRight />
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function RevokedActionsAlert(): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert className={alertVariants.warning}>
      <CircleAlert />
      <AlertTitle className="text-sm font-medium">
        {t("setting/trusted-device-dialogs.revoked.trust_again_title")}
      </AlertTitle>
      <AlertDescription>
        {t("setting/trusted-device-dialogs.revoked.trust_again_description")}
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceRevokedDialog;
