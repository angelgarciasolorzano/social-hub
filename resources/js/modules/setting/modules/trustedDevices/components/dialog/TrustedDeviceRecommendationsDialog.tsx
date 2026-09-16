import type { JSX } from "react";

import { ChevronRight, CircleAlert, Lightbulb, type LucideIcon } from "lucide-react";

import {
  trustedDevicePreviewAlert,
  trustedDevicePreviewCopy,
  type TrustedDevicePreviewRow,
  trustedDevicePreviewRows,
  trustedDeviceRecommendations,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceRecommendations";

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
  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-4xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Lightbulb className="h-5 w-5 text-muted-foreground" />
              Recomendaciones de seguridad
            </div>
          </DialogTitle>
          <DialogDescription>
            Sigue estas recomendaciones para mantener tu cuenta y dispositivos de confianza seguros.
          </DialogDescription>
        </DialogHeader>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
          <div className="space-y-4 lg:col-span-3">
            {trustedDeviceRecommendations.map((recommendation) => (
              <RecommendationCard
                description={recommendation.description}
                icon={recommendation.icon}
                iconColor={recommendation.iconColor}
                key={recommendation.title}
                title={recommendation.title}
              />
            ))}
          </div>

          <div className="lg:col-span-2">
            <DevicesPreviewSidebar
              copy={trustedDevicePreviewCopy}
              previews={trustedDevicePreviewRows}
            />
          </div>
        </div>

        <Alert className={alertVariants.info}>
          <CircleAlert />
          <AlertTitle>Estas recomendaciones te ayudan a mantener tu cuenta segura</AlertTitle>
          <AlertDescription>
            Los dispositivos de confianza te permiten iniciar sesion mas rapido, pero es importante
            revisarlos y mantener solo los que utilizas.
          </AlertDescription>
        </Alert>

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface RecommendationCardProps {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
}

function RecommendationCard({
  description,
  icon: Icon,
  iconColor,
  title,
}: RecommendationCardProps): JSX.Element {
  return (
    <div className="flex items-start gap-4 rounded-xl border bg-card p-4 shadow-sm dark:bg-input/20">
      <div
        className={cn(
          "flex h-10 w-10 shrink-0 rounded-full p-2",
          iconColorVariants[iconColor].iconBgClass,
        )}
      >
        <Icon className={cn("h-6 w-6", iconColorVariants[iconColor].iconFgClass)} />
      </div>

      <div className="space-y-1">
        <h4 className="text-sm font-semibold">{title}</h4>
        <p className="text-sm text-muted-foreground">{description}</p>
      </div>
    </div>
  );
}

interface DevicesPreviewSidebarProps {
  copy: typeof trustedDevicePreviewCopy;
  previews: readonly TrustedDevicePreviewRow[];
}

function DevicesPreviewSidebar({ copy, previews }: DevicesPreviewSidebarProps): JSX.Element {
  return (
    <div className="flex h-full flex-col gap-6 rounded-xl border bg-muted/40 p-5 dark:bg-muted/20">
      <div className="space-y-3 rounded-xl border bg-card p-4 dark:bg-muted/30">
        {previews.map((preview, index) => (
          <div className="space-y-3" key={preview.device.name}>
            <DevicePreviewCard preview={preview} />

            {index < previews.length - 1 && <Separator />}
          </div>
        ))}
      </div>

      <div className="space-y-3">
        <h4 className="text-base leading-tight font-semibold">{copy.title}</h4>
        <p className="text-sm text-muted-foreground">{copy.body}</p>

        <Alert className={alertVariants.success}>
          <CircleAlert />
          <AlertTitle>{trustedDevicePreviewAlert.title}</AlertTitle>
          <AlertDescription>{trustedDevicePreviewAlert.body}</AlertDescription>
        </Alert>
      </div>
    </div>
  );
}

interface DevicePreviewCardProps {
  preview: TrustedDevicePreviewRow;
}

function DevicePreviewCard({ preview }: DevicePreviewCardProps): JSX.Element {
  const DeviceIcon = preview.device.icon;
  const BadgeIcon: LucideIcon = ChevronRight;

  return (
    <div className="flex items-center justify-between gap-2">
      <div className="flex min-w-0 items-center gap-2">
        <div
          className={cn(
            "flex h-9 w-9 shrink-0 rounded-md p-2",
            iconColorVariants[preview.device.iconVariant].iconBgClass,
          )}
        >
          <DeviceIcon
            className={cn("h-5 w-5", iconColorVariants[preview.device.iconVariant].iconFgClass)}
          />
        </div>

        <div className="min-w-0 space-y-0.5">
          <p className="truncate text-sm font-medium">{preview.device.name}</p>
          <p className="truncate text-xs text-muted-foreground">{preview.device.subtitle}</p>
        </div>
      </div>

      <div
        className={cn(
          "flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium",
          iconColorVariants[preview.badge.variant].iconBgClass,
          iconColorVariants[preview.badge.variant].iconFgClass,
        )}
      >
        {preview.badge.label}
        <BadgeIcon className="h-3 w-3" />
      </div>
    </div>
  );
}

export default TrustedDeviceRecommendationsDialog;
