import { type JSX } from "react";

import { Link } from "@inertiajs/react";

import { ArrowRight, Clock, ShieldCheck } from "lucide-react";

import { useDialogFocusRestoration } from "@/modules/setting/shared/hooks/useDialogFocusRestoration";

import TrustedDeviceIndexController from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent } from "@/shared/components/shadcn/ui/card";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/shared/components/shadcn/ui/tooltip";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { alertVariants } from "@/shared/lib/styling";

interface TwoFactorActivationDetailsDialogProps {
  isOpen: boolean;
  setOpen: (open: boolean) => void;
  confirmedAt: string | null;
}

function TwoFactorActivationDetailsDialog({
  isOpen,
  setOpen,
  confirmedAt,
}: TwoFactorActivationDetailsDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { formatActivationDate, formatActivationTime } = useDateTime();
  const dialogFocusRestoration = useDialogFocusRestoration();

  const activationDate = formatActivationDate(confirmedAt);
  const activationTime = formatActivationTime(confirmedAt);

  return (
    <Dialog open={isOpen} onOpenChange={setOpen}>
      <DialogContent {...dialogFocusRestoration}>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2">
              <Clock className="h-5 w-5 text-muted-foreground" />
              {t("setting/two-factor.labels.activation_date")}
            </h2>
          </DialogTitle>
          <DialogDescription>
            {t("setting/two-factor.activation_details.description")}
          </DialogDescription>
        </DialogHeader>

        <Card className="gap-0 py-0 shadow-xs dark:bg-input/20">
          <CardContent className="px-0">
            <dl
              className="grid items-center justify-center"
              style={{ gridTemplateColumns: "repeat(2, minmax(0, 1fr))" }}
            >
              <div
                className="flex flex-col items-center gap-1 border-b border-border px-6 py-6"
                style={{ borderRight: "1px solid var(--border)" }}
              >
                <dt className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("setting/two-factor.activation_details.time")}
                </dt>
                <dd className="text-sm font-semibold">{activationTime}</dd>
              </div>

              <div className="flex flex-col items-center gap-1 border-b border-border px-6 py-6">
                <dt className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("setting/two-factor.activation_details.method")}
                </dt>
                <dd className="text-sm font-semibold">
                  {t("setting/two-factor.labels.method_totp")}
                </dd>
              </div>

              <div
                className="flex flex-col items-center gap-2 px-6 py-6"
                style={{ gridColumn: "1 / -1" }}
              >
                <dt className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("setting/two-factor.labels.activation_date")}
                </dt>
                <dd className="text-sm font-semibold uppercase">{activationDate}</dd>
              </div>
            </dl>
          </CardContent>
        </Card>

        <Alert className={alertVariants.success}>
          <ShieldCheck />
          <AlertTitle>{t("setting/two-factor.activation_details.protect_title")}</AlertTitle>
          <AlertDescription className="flex items-start gap-2">
            {t("setting/two-factor.activation_details.protect_description")}
            <Tooltip>
              <TooltipTrigger asChild>
                <Button asChild variant="outline" size="icon">
                  <Link
                    aria-label={t("setting/two-factor.activation_details.go_to_devices")}
                    href={TrustedDeviceIndexController()}
                  >
                    <ArrowRight aria-hidden="true" />
                  </Link>
                </Button>
              </TooltipTrigger>
              <TooltipContent side="top" sideOffset={4}>
                {t("setting/two-factor.activation_details.go_to_devices_hint")}
              </TooltipContent>
            </Tooltip>
          </AlertDescription>
        </Alert>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline">
              {t("setting/common.actions.close")}
            </Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default TwoFactorActivationDetailsDialog;
