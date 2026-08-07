import type { JSX } from "react";
import { useState } from "react";

import { router, usePage } from "@inertiajs/react";

import {
  Bolt,
  ChevronDown,
  ChevronRight,
  ClipboardCheck,
  Clock4,
  Info,
  Key,
  MonitorSmartphone,
  ShieldCheck,
} from "lucide-react";

import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/shadcn/ui/card";
import { Progress } from "@/shared/components/shadcn/ui/progress";

import { useDialog } from "@/shared/hooks/useDialog";

import { cn } from "@/shared/lib";
import { badgeVariants, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

import DisabledTwoFactorDialog from "../components/dialog/twoFactorEnable/DisabledTwoFactorDialog";
import RegenerateCodesDialog from "../components/dialog/twoFactorEnable/RegenerateCodesDialog";
import { OptionCard } from "../components/ui/OptionCard";
import type { SumaryCardAction, SumaryCardItem } from "../components/ui/SummaryCard";
import SummaryCard from "../components/ui/SummaryCard";
import TwoFactorDevices from "../components/ui/twoFactorEnable/TwoFactorDevices";
import TwoFactorRecoveryCodes from "../components/ui/twoFactorEnable/TwoFactorRecoveryCodes";
import type { TwoFactorSecurityOptionKey } from "../data/twoFactorEnable";
import {
  twoFactorSafetyTips,
  twoFactorSecurityOptions,
  twoFactorSecurityOptionsKey,
} from "../data/twoFactorEnable";
import { useTwoFactorAuth } from "../hooks/useTwoFactorAuth";
import type { TrustedDevice } from "../types/trustedDevice";

interface TwoFactorEnableProps {
  trustedDevices: TrustedDevice[];
  trustedDevicesForRevoke: TrustedDevice[];
}

type SlotContent = "codes" | "devices";

function TwoFactorEnable({
  trustedDevices,
  trustedDevicesForRevoke,
}: TwoFactorEnableProps): JSX.Element {
  const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();

  const { open: showRegenerateCodesDialog, setOpen: setShowRegenerateCodesDialog } = useDialog();
  const { open: showDisabledTwoFactorDialog, setOpen: setShowDisabledTwoFactorDialog } =
    useDialog();

  const [selectedContent, setSelectedContent] = useState<SlotContent>("codes");

  const handleViewTrustedDevices = (): void => {
    router.reload({
      only: ["trustedDevices"],
      onSuccess: () => {
        setSelectedContent("devices");
      },
    });
  };

  const handleSecurityOptionClick = (optionKey: TwoFactorSecurityOptionKey) => {
    switch (optionKey) {
      case twoFactorSecurityOptionsKey.backupCodes:
        setSelectedContent("codes");

        break;

      case twoFactorSecurityOptionsKey.regenerateCodes:
        setShowRegenerateCodesDialog(true);

        break;

      case twoFactorSecurityOptionsKey.disable2FA:
        setShowDisabledTwoFactorDialog(true);

        break;

      default:
        break;
    }
  };

  return (
    <div className="flex gap-4">
      <div className="flex min-w-0 flex-1 flex-col gap-8">
        <TwoFactorTitle />

        <OptionCard
          title="Opciones de seguridad"
          options={twoFactorSecurityOptions}
          onOptionClick={handleSecurityOptionClick}
        />

        <TwoFactorSecuritySummary
          recoveryCodesList={recoveryCodesList}
          onViewTrustedDevices={handleViewTrustedDevices}
        />

        <TwoFactorSafetyTips />
      </div>

      <Card className="w-full max-w-sm shrink-0">
        <CardHeader>
          <CardTitle>
            {selectedContent === "codes" ? "Códigos de respaldo" : "Dispositivos de confianza"}
          </CardTitle>
        </CardHeader>

        <CardContent className="flex flex-col gap-6">
          {selectedContent === "codes" ? (
            <TwoFactorRecoveryCodes
              errors={errors}
              fetchRecoveryCodes={fetchRecoveryCodes}
              recoveryCodesList={recoveryCodesList}
            />
          ) : (
            <TwoFactorDevices
              devices={trustedDevices}
              trustedDevicesForRevoke={trustedDevicesForRevoke}
            />
          )}
        </CardContent>
      </Card>

      <RegenerateCodesDialog
        isOpen={showRegenerateCodesDialog}
        setOpen={setShowRegenerateCodesDialog}
        fetchRecoveryCodes={fetchRecoveryCodes}
      />

      <DisabledTwoFactorDialog
        isOpen={showDisabledTwoFactorDialog}
        setOpen={setShowDisabledTwoFactorDialog}
      />
    </div>
  );
}

function TwoFactorTitle(): JSX.Element {
  return (
    <div className="flex items-start gap-6 rounded-xl border bg-card p-6 shadow-sm">
      <div className={cn(iconColorVariants.green.iconBgClass, "rounded-3xl p-2")}>
        <ShieldCheck className={cn("h-12 w-12", iconColorVariants.green.iconFgClass)} />
      </div>

      <div className="flex flex-1 flex-col gap-3">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl font-semibold">2FA está activo</h2>

          <p className="text-sm text-muted-foreground">
            Tu cuenta está protegida con autenticación de dos factores.
          </p>
        </div>

        <div className="mt-4 grid grid-cols-2 gap-4">
          <div className="flex items-center gap-2">
            <ClipboardCheck className="h-4 w-4 text-green-700 dark:text-green-500" />

            <span className="text-sm text-muted-foreground">
              <strong>Método: </strong>
              TOTP (Aplicación)
            </span>
          </div>

          <div className="flex items-center gap-2">
            <Clock4 className="h-4 w-4 text-muted-foreground" />

            <span className="text-sm text-muted-foreground">
              <strong>Última verificación: </strong>
              hace 2 horas
            </span>
          </div>
        </div>
      </div>

      <Button>
        <Bolt />
        Administrar
        <ChevronDown />
      </Button>
    </div>
  );
}

interface TwoFactorSecuritySummaryProps {
  recoveryCodesList: string[];
  onViewTrustedDevices: () => void;
}

type TwoFactorSecuritySummaryPageProps = SharedData & {
  trustedDevicesCount?: number;
};

function TwoFactorSecuritySummary({
  recoveryCodesList,
  onViewTrustedDevices,
}: TwoFactorSecuritySummaryProps): JSX.Element {
  const { trustedDevicesCount = 0 } = usePage<TwoFactorSecuritySummaryPageProps>().props;

  const trustedDevicesLabel =
    trustedDevicesCount === 1
      ? "1 dispositivo de confianza configurado."
      : `${trustedDevicesCount} dispositivos de confianza configurados.`;

  const summarySecurity: SumaryCardItem[] = [
    {
      key: "status-2fa",
      title: "Estado de 2FA",
      description: "La autenticación de dos factores está activa en tu cuenta.",
      icon: ShieldCheck,
      iconColor: "green",
      action: { type: "badge", variant: "default", label: "Activado" },
    },
    {
      key: "trusted-devices",
      title: "Dispositivos de confianza",
      description: trustedDevicesLabel,
      icon: MonitorSmartphone,
      iconColor: "violet",
      action: {
        type: "button",
        label: trustedDevicesCount === 0 ? "Configurar" : "Ver dispositivos",
        onClick: onViewTrustedDevices,
      },
    },
    {
      key: "backup-codes",
      title: "Códigos de respaldo",
      description: `Tienes ${recoveryCodesList.length} de 8 códigos disponibles.`,
      icon: Key,
      iconColor: "orange",
      action: {
        type: "progress",
        current: recoveryCodesList.length,
        total: 8,
      },
    },
    {
      key: "activation-date",
      title: "Fecha de activación",
      description: "Activaste 2FA el 15 de marzo 2024, 11:45 AM",
      icon: Clock4,
      iconColor: "blue",
      action: {
        type: "chevron",
        onClick: () => {
          // TODO: open activation details dialog
        },
      },
    },
  ];

  const renderAction = (action: SumaryCardAction) => {
    switch (action.type) {
      case "badge": {
        const badgeClassName = action.variant === "default" ? badgeVariants.success : "";

        return (
          <Badge variant={action.variant} className={cn(badgeClassName)}>
            {action.label}
          </Badge>
        );
      }
      case "button":
        return (
          <Button className="cursor-pointer gap-2" onClick={action.onClick}>
            {action.label}
            <ChevronRight className="h-4 w-4" />
          </Button>
        );
      case "progress": {
        const percentage = (action.current / action.total) * 100;

        return (
          <div className="flex min-w-50 items-center gap-4">
            <span className="text-sm whitespace-nowrap text-muted-foreground">
              {action.current}/{action.total}
            </span>

            <Progress value={percentage} className="w-full" />
          </div>
        );
      }
      case "chevron":
        return (
          <button
            onClick={action.onClick}
            className="cursor-pointer rounded-full p-1 transition-colors hover:bg-accent"
            type="button"
            aria-label="Ver detalles"
          >
            <ChevronRight className="h-5 w-5 text-muted-foreground" />
          </button>
        );
      case "none":
        return null;
    }
  };

  return (
    <SummaryCard
      title="Resumen de seguridad"
      data={summarySecurity}
      showLastSeparator
      renderAction={renderAction}
    />
  );
}

function TwoFactorSafetyTips(): JSX.Element {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center">
          <Info className="mr-2 h-6 w-6 text-purple-700 dark:text-purple-500" />
          Consejos de seguridad
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-1 gap-6">
        {twoFactorSafetyTips.map((tip) => {
          const Icon = tip.icon;

          return (
            <div key={tip.key} className="flex gap-4">
              <div
                className={cn(
                  iconColorVariants.purple.iconBgClass,
                  "flex h-12 w-12 shrink-0 rounded-md p-2",
                )}
              >
                <Icon className={cn("h-8 w-8", iconColorVariants.purple.iconFgClass)} />
              </div>

              <div className="flex flex-1 flex-col gap-0.5">
                <h4 className="text-sm font-medium">{tip.title}</h4>

                <p className="text-sm text-muted-foreground">{tip.description}</p>
              </div>
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}

export default TwoFactorEnable;
