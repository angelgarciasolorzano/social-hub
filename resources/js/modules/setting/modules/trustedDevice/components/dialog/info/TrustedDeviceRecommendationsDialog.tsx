import { type JSX } from "react";

import { Info, Lightbulb, type LucideIcon, ShieldCheck } from "lucide-react";

import {
  trustedDevicePreviewAlert,
  type TrustedDevicePreviewRow,
  trustedDevicePreviewRows,
  trustedDeviceRecommendations,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceRecommendations";
import { useDialogFocusRestoration } from "@/modules/setting/shared/hooks/useDialogFocusRestoration";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { cn } from "@/shared/lib";
import { alertVariants, type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

interface TrustedDeviceRecommendationsDialogProps {
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceRecommendationsDialog({
  open,
  onClose,
}: TrustedDeviceRecommendationsDialogProps): JSX.Element {
  const dialogFocusRestoration = useDialogFocusRestoration();

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-4xl" {...dialogFocusRestoration}>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <Lightbulb aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Recomendaciones de seguridad
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            Sigue estas recomendaciones para mantener tu cuenta y dispositivos de confianza seguros.
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <div className="grid grid-cols-1 gap-5 lg:grid-cols-5">
          <ul
            className="list-none divide-y self-start overflow-hidden rounded-xl border lg:col-span-3 dark:bg-muted/20"
            role="list"
          >
            {trustedDeviceRecommendations.map((recommendation) => (
              <RecommendationItem
                description={recommendation.description}
                icon={recommendation.icon}
                iconColor={recommendation.iconColor}
                key={recommendation.title}
                title={recommendation.title}
              />
            ))}
          </ul>

          <div className="flex flex-col gap-4 lg:col-span-2">
            <DevicesPreviewSidebar previewRows={trustedDevicePreviewRows} />

            <Alert className={alertVariants.success}>
              <ShieldCheck aria-hidden="true" />
              <AlertTitle className="text-sm font-medium">
                {trustedDevicePreviewAlert.title}
              </AlertTitle>
              <AlertDescription className="text-sm font-normal">
                {trustedDevicePreviewAlert.body}
              </AlertDescription>
            </Alert>
          </div>
        </div>

        <Separator />

        <DialogFooter className="items-center sm:justify-between">
          <p className="flex items-start gap-2 text-xs font-normal text-muted-foreground sm:max-w-md">
            <Info aria-hidden="true" className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            Los dispositivos de confianza agilizan tu inicio de sesión, pero conviene revisarlos de
            vez en cuando.
          </p>
          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface RecommendationItemProps {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
}

function RecommendationItem({
  description,
  icon: Icon,
  iconColor,
  title,
}: RecommendationItemProps): JSX.Element {
  return (
    <li className="flex items-start gap-3.5 px-4 py-4">
      <div
        aria-hidden="true"
        className={cn(
          "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg",
          iconColorVariants[iconColor].iconBgClass,
        )}
      >
        <Icon className={cn("h-4.5 w-4.5", iconColorVariants[iconColor].iconFgClass)} />
      </div>

      <div className="space-y-0.5">
        <h3 className="text-sm font-medium">{title}</h3>
        <p className="text-[13px] leading-relaxed font-normal text-muted-foreground">
          {description}
        </p>
      </div>
    </li>
  );
}

interface DevicesPreviewSidebarProps {
  previewRows: readonly TrustedDevicePreviewRow[];
}

function DevicesPreviewSidebar({ previewRows }: DevicesPreviewSidebarProps): JSX.Element {
  return (
    <div className="flex flex-col gap-4 rounded-xl border bg-muted/40 p-4 dark:bg-muted/20">
      <div className="flex items-center justify-between">
        <h3 className="text-xs font-medium text-muted-foreground">Así se verá en tu lista</h3>
        <span className="rounded-full border bg-card px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
          Ejemplo
        </span>
      </div>

      <div className="divide-y rounded-xl border bg-card dark:bg-muted/30">
        {previewRows.map((previewRow) => (
          <DevicePreviewRow key={previewRow.device.name} previewRow={previewRow} />
        ))}
      </div>
    </div>
  );
}

interface DevicePreviewRowProps {
  previewRow: TrustedDevicePreviewRow;
}

function DevicePreviewRow({ previewRow }: DevicePreviewRowProps): JSX.Element {
  const DeviceIcon = previewRow.device.icon;

  return (
    <div className="flex items-center justify-between gap-2 px-3.5 py-3">
      <div className="flex min-w-0 items-center gap-3">
        <div
          aria-hidden="true"
          className={cn(
            "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg",
            iconColorVariants[previewRow.device.iconVariant].iconBgClass,
          )}
        >
          <DeviceIcon
            className={cn(
              "h-4.5 w-4.5",
              iconColorVariants[previewRow.device.iconVariant].iconFgClass,
            )}
          />
        </div>

        <div className="min-w-0">
          <p className="truncate text-sm font-medium">{previewRow.device.name}</p>
          <p className="truncate text-xs font-normal text-muted-foreground">
            {previewRow.device.subtitle}
          </p>
        </div>
      </div>

      <span
        className={cn(
          "flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium",
          iconColorVariants[previewRow.badge.variant].iconBgClass,
          iconColorVariants[previewRow.badge.variant].iconFgClass,
        )}
      >
        <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-current" />
        {previewRow.badge.label}
      </span>
    </div>
  );
}

export default TrustedDeviceRecommendationsDialog;
