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

import { cn } from "@/shared/lib";

import DisabledTwoFactorDialog from "../components/dialog/twoFactorEnable/DisabledTwoFactorDialog";
import RegenerateCodesDialog from "../components/dialog/twoFactorEnable/RegenerateCodesDialog";
import { OptionCard } from "../components/ui/OptionCard";
import type { SumaryCardAction, SumaryCardItem } from "../components/ui/SummaryCard";
import SummaryCard from "../components/ui/SummaryCard";
import TwoFactorRecoveryCodes from "../components/ui/twoFactorEnable/TwoFactorRecoveryCodes";
import type { TwoFactorSecurityOptionKey } from "../data/twoFactorEnable";
import {
  twoFactorSafetyTips,
  twoFactorSecurityOptions,
  twoFactorSecurityOptionsKey,
} from "../data/twoFactorEnable";
import { useTwoFactorAuth } from "../hooks/useTwoFactorAuth";
import { useTwoFactorEnable } from "../hooks/useTwoFactorEnable";

function TwoFactorAuthenticationEnable() {
  const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();
  const {
    showRegenerateCodesDialog,
    setShowRegenerateCodesDialog,
    showDisabledTwoFactorDialog,
    setShowDisabledTwoFactorDialog,
  } = useTwoFactorEnable();
  // Handler que recibe la key del card clickeado
  const handleSecurityOptionClick = (optionKey: TwoFactorSecurityOptionKey) => {
    switch (optionKey) {
      case twoFactorSecurityOptionsKey.backupCodes:
        console.log("Mostrando códigos de respaldo...");
        // Aquí podrías abrir un modal, hacer una petición, etc.
        // Por ejemplo: setShowBackupCodesModal(true);
        break;

      case twoFactorSecurityOptionsKey.regenerateCodes:
        setShowRegenerateCodesDialog(true);
        break;

      case twoFactorSecurityOptionsKey.disable2FA:
        setShowDisabledTwoFactorDialog(true);
        break;

      default:
        console.log("Acción no reconocida:", optionKey);
    }
  };

  return (
    <div className="flex gap-4">
      <div className="flex flex-col gap-8">
        <TwoFactorTitle />

        <OptionCard
          title="Opciones de seguridad"
          options={twoFactorSecurityOptions}
          onOptionClick={handleSecurityOptionClick}
        />

        <TwoFactorSecuritySummary recoveryCodesList={recoveryCodesList} />

        <TwoFactorSafatyTips />
      </div>

      <div>
        <TwoFactorRecoveryCodes
          errors={errors}
          fetchRecoveryCodes={fetchRecoveryCodes}
          recoveryCodesList={recoveryCodesList}
        />
      </div>

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

function TwoFactorTitle() {
  return (
    <div className="flex items-start gap-6 rounded-md border border-green-200 bg-green-300/5 p-8 shadow-sm dark:border-green-500/20 dark:bg-green-900/5">
      <div className="rounded-3xl bg-green-200/50 p-2 dark:bg-green-900/20">
        <ShieldCheck className="h-12 w-12 text-green-700 dark:text-green-500" />
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

      <Button variant="outline">
        <Bolt />
        Administrar
        <ChevronDown />
      </Button>
    </div>
  );
}

interface TwoFactorSecuritySummaryProps {
  recoveryCodesList: string[];
}

function TwoFactorSecuritySummary({ recoveryCodesList }: TwoFactorSecuritySummaryProps) {
  const summarySecurity: SumaryCardItem[] = [
    {
      key: "status-2fa",
      title: "Estado de 2FA",
      description: "La autenticación de dos factores está activa en tu cuenta.",
      icon: ShieldCheck,
      iconBgColor: "bg-green-100/50 dark:bg-green-900/20",
      iconColor: "text-green-700 dark:text-green-500",
      action: { type: "badge", variant: "default", label: "Activado" },
    },
    {
      key: "trusted-devices",
      title: "Dispositivos de confianza",
      description: "Has configurado 2 dispositivos de confianza.",
      icon: MonitorSmartphone,
      iconBgColor: "bg-violet-100/50 dark:bg-violet-900/20",
      iconColor: "text-violet-700 dark:text-violet-500",
      action: {
        type: "button",
        label: "Ver dispositivos",
        onClick: () => console.log("Ver dispositivos de confianza"),
      },
    },
    {
      key: "backup-codes",
      title: "Códigos de respaldo",
      description: `Tienes ${recoveryCodesList.length} de 8 códigos disponibles.`,
      icon: Key,
      iconBgColor: "bg-orange-100/50 dark:bg-orange-900/20",
      iconColor: "text-orange-700 dark:text-orange-500",
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
      iconBgColor: "bg-blue-100/50 dark:bg-blue-900/20",
      iconColor: "text-blue-700 dark:text-blue-500",
      action: { type: "chevron", onClick: () => console.log("Ver detalles de activación") },
    },
  ];

  const renderAction = (action: SumaryCardAction) => {
    switch (action.type) {
      case "badge": {
        const badgeClassName =
          action.variant === "default"
            ? "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
            : "";

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

function TwoFactorSafatyTips() {
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
              <div className="flex h-12 w-12 shrink-0 rounded-md bg-purple-100/50 p-2 dark:bg-purple-900/20">
                <Icon className="h-8 w-8 text-purple-700 dark:text-purple-500" />
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

export default TwoFactorAuthenticationEnable;
