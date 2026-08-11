import { type JSX, useEffect } from "react";

import { router, usePage } from "@inertiajs/react";

import type { LucideIcon } from "lucide-react";
import { Calendar, Clock, Info, ShieldCheck, Smartphone } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent } from "@/shared/components/shadcn/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { cn } from "@/shared/lib";
import { alertVariants, type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

import type { TrustedDevice } from "../../../types/trustedDevice";
import { formatActivationDate, formatActivationTime } from "../../../utils/dateTime";
import { deviceLabel } from "../../../utils/trustedDevice";

interface TwoFactorActivationDetailsDialogProps {
  isOpen: boolean;
  setOpen: (open: boolean) => void;
  confirmedAt: string | null;
}

type TwoFactorEnablePageProps = SharedData & {
  firstTrustedDevice?: TrustedDevice | null;
};

const TWO_FACTOR_METHOD_LABEL = "TOTP (Aplicación)";
const NO_TRUSTED_DEVICES_LABEL = "No hay dispositivos registrados";

const twoFactorActivationDetailKey = {
  activationDate: "activation-date",
  activationTime: "activation-time",
  verificationMethod: "verification-method",
  firstTrustedDevice: "first-trusted-device",
} as const;

type TwoFactorActivationDetailKey =
  (typeof twoFactorActivationDetailKey)[keyof typeof twoFactorActivationDetailKey];

interface TwoFactorActivationDetail {
  key: TwoFactorActivationDetailKey;
  icon: LucideIcon;
  iconVariant: IconColorVariant;
  label: string;
  value: string;
  valueClassName?: string;
}

function TwoFactorActivationDetailsDialog({
  isOpen,
  setOpen,
  confirmedAt,
}: TwoFactorActivationDetailsDialogProps): JSX.Element {
  const { firstTrustedDevice = null } = usePage<TwoFactorEnablePageProps>().props;

  const activationDate = formatActivationDate(confirmedAt);
  const activationTime = formatActivationTime(confirmedAt);

  const firstDeviceLabel =
    firstTrustedDevice !== null ? deviceLabel(firstTrustedDevice) : NO_TRUSTED_DEVICES_LABEL;

  useEffect(() => {
    router.reload({
      only: ["firstTrustedDevice"],
    });
  }, []);

  const details: TwoFactorActivationDetail[] = [
    {
      key: twoFactorActivationDetailKey.activationDate,
      icon: Calendar,
      iconVariant: "blue",
      label: "Fecha de activación",
      value: activationDate,
    },
    {
      key: twoFactorActivationDetailKey.activationTime,
      icon: Clock,
      iconVariant: "gray",
      label: "Hora de activación",
      value: activationTime,
    },
    {
      key: twoFactorActivationDetailKey.verificationMethod,
      icon: ShieldCheck,
      iconVariant: "gray",
      label: "Método de verificación",
      value: TWO_FACTOR_METHOD_LABEL,
    },
    {
      key: twoFactorActivationDetailKey.firstTrustedDevice,
      icon: Smartphone,
      iconVariant: "orange",
      label: "Primer dispositivo utilizado",
      value: firstDeviceLabel,
      valueClassName: "max-w-40 truncate",
    },
  ];

  return (
    <Dialog open={isOpen} onOpenChange={setOpen}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Clock className="h-5 w-5 text-muted-foreground" />
              Fecha de activación
            </div>
          </DialogTitle>
          <DialogDescription>
            Aquí puedes ver cuándo activaste la autenticación de dos factores (2FA) en tu cuenta.
          </DialogDescription>
        </DialogHeader>

        <Alert className={alertVariants.info}>
          <Info />
          <AlertTitle>Desde esta fecha, tu cuenta está protegida con 2FA.</AlertTitle>
          <AlertDescription>
            Es recomendable mantener esta protección activa en todo momento.
          </AlertDescription>
        </Alert>

        <Card>
          <CardContent className="space-y-4">
            {details.map((detail) => {
              const Icon = detail.icon;

              return (
                <div key={detail.key} className="flex items-center justify-between">
                  <div className="flex items-center gap-2.5">
                    <div
                      className={cn(
                        "flex h-9 w-9 shrink-0 items-center justify-center rounded-md",
                        iconColorVariants[detail.iconVariant].iconBgClass,
                      )}
                    >
                      <Icon
                        className={cn("h-4 w-4", iconColorVariants[detail.iconVariant].iconFgClass)}
                      />
                    </div>

                    <span className="text-sm text-muted-foreground">{detail.label}</span>
                  </div>

                  <p className={cn("text-sm font-medium", detail.valueClassName)}>{detail.value}</p>
                </div>
              );
            })}
          </CardContent>
        </Card>

        <Alert className={alertVariants.success}>
          <ShieldCheck />
          <AlertTitle>Protege tu cuenta</AlertTitle>
          <AlertDescription>
            Si no reconoces esta actividad, te recomendamos cambiar tu contraseña y revisar tus
            dispositivos de confianza.
          </AlertDescription>
        </Alert>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setOpen(false);
            }}
          >
            Cerrar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default TwoFactorActivationDetailsDialog;
