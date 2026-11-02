import type { JSX } from "react";
import { Fragment, useState } from "react";

import { usePage } from "@inertiajs/react";

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

import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";

import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader } from "@/shared/components/shadcn/ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";
import { Progress } from "@/shared/components/shadcn/ui/progress";

import { useDateTime, useTranslation } from "@/shared/hooks";
import { useDialog } from "@/shared/hooks/useDialog";

import { cn } from "@/shared/lib";
import { badgeVariants, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

import DisabledTwoFactorDialog from "../components/dialog/twoFactorEnable/DisabledTwoFactorDialog";
import RegenerateCodesDialog from "../components/dialog/twoFactorEnable/RegenerateCodesDialog";
import TwoFactorActivationDetailsDialog from "../components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog";
import { OptionCard } from "../components/ui/OptionCard";
import type { SumaryCardAction, SumaryCardItem } from "../components/ui/SummaryCard";
import SummaryCard from "../components/ui/SummaryCard";
import TwoFactorDevices from "../components/ui/twoFactorEnable/TwoFactorDevices";
import TwoFactorRecoveryCodes from "../components/ui/twoFactorEnable/TwoFactorRecoveryCodes";
import type { TwoFactorManageActionKey, TwoFactorSecurityOptionKey } from "../data/twoFactorEnable";
import {
  twoFactorManageActionKey,
  twoFactorManageActions,
  twoFactorSafetyTips,
  twoFactorSecurityOptions,
  twoFactorSecurityOptionsKey,
} from "../data/twoFactorEnable";
import { useTwoFactorAuth } from "../hooks/useTwoFactorAuth";

const RECOVERY_CODES_TOTAL = 8;

type SlotContent = "codes" | "devices";

type SecurityDialogKind = "regenerateCodes" | "disableTwoFactor";

interface SecurityDialogState extends DialogClosingState {
  kind: SecurityDialogKind;
}

type TwoFactorSharedSecurityAction =
  TwoFactorSecurityOptionKey | typeof twoFactorManageActionKey.viewCodes;

type TwoFactorEnablePageProps = SharedData & {
  twoFactorConfirmedAt?: string | null;
};

function TwoFactorEnable(): JSX.Element {
  const { t } = useTranslation();
  const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();

  const securityOptions = twoFactorSecurityOptions.map((securityOption) => ({
    ...securityOption,
    title: t(securityOption.title),
    description: t(securityOption.description),
  }));

  const { twoFactorConfirmedAt = null } = usePage<TwoFactorEnablePageProps>().props;

  const securityDialog = useDialog<SecurityDialogState | null>(null);
  const activationDetailsDialog = useDialog();

  const [selectedContent, setSelectedContent] = useState<SlotContent>("codes");

  const handleViewTrustedDevices = (): void => {
    setSelectedContent("devices");
  };

  const handleViewActivationDetails = (): void => {
    activationDetailsDialog.setOpen(true);
  };

  const handleSharedSecurityAction = (action: TwoFactorSharedSecurityAction): void => {
    switch (action) {
      case twoFactorSecurityOptionsKey.backupCodes:
      case twoFactorManageActionKey.viewCodes:
        setSelectedContent("codes");

        break;

      case twoFactorSecurityOptionsKey.regenerateCodes:
        securityDialog.show({ kind: "regenerateCodes", closing: false });

        break;

      case twoFactorSecurityOptionsKey.disable2FA:
        securityDialog.show({ kind: "disableTwoFactor", closing: false });

        break;

      default:
        break;
    }
  };

  const handleSecurityDialogClose = createDialogCloseHandler(securityDialog);

  const handleSecurityDialogOpenChange = (open: boolean): void => {
    if (!open) {
      handleSecurityDialogClose();
    }
  };

  const handleSecurityOptionClick = (optionKey: TwoFactorSecurityOptionKey): void => {
    handleSharedSecurityAction(optionKey);
  };

  const handleManageAction = (action: TwoFactorManageActionKey): void => {
    if (action === twoFactorManageActionKey.viewDevices) {
      handleViewTrustedDevices();

      return;
    }

    handleSharedSecurityAction(action);
  };

  const renderSecurityDialog = (): JSX.Element | null => {
    if (securityDialog.state === null) {
      return null;
    }

    const isClosing = securityDialog.state.closing;

    switch (securityDialog.state.kind) {
      case "regenerateCodes":
        return (
          <RegenerateCodesDialog
            fetchRecoveryCodes={fetchRecoveryCodes}
            isOpen={!isClosing}
            setOpen={handleSecurityDialogOpenChange}
          />
        );

      case "disableTwoFactor":
        return (
          <DisabledTwoFactorDialog isOpen={!isClosing} setOpen={handleSecurityDialogOpenChange} />
        );

      default:
        return null;
    }
  };

  return (
    <div className="flex min-w-0 flex-col gap-6 xl:flex-row">
      <div className="flex min-w-0 flex-1 flex-col gap-6">
        <TwoFactorTitle onManageAction={handleManageAction} />

        <OptionCard
          title={t("setting/two-factor.enabled_view.security_options")}
          options={securityOptions}
          onOptionClick={handleSecurityOptionClick}
        />

        <TwoFactorSecuritySummary
          recoveryCodesList={recoveryCodesList}
          onViewTrustedDevices={handleViewTrustedDevices}
          onActivationDetailsClick={handleViewActivationDetails}
          twoFactorConfirmedAt={twoFactorConfirmedAt}
        />

        <TwoFactorSafetyTips />
      </div>

      <Card className="w-full shadow-xs xl:max-w-sm xl:shrink-0 xl:self-start">
        <CardHeader>
          <h2 className="text-lg leading-none font-semibold">
            {selectedContent === "codes"
              ? t("setting/two-factor.labels.backup_codes")
              : t("setting/two-factor.labels.trusted_devices")}
          </h2>
        </CardHeader>

        <CardContent className="flex flex-col gap-6">
          {selectedContent === "codes" ? (
            <TwoFactorRecoveryCodes
              errors={errors}
              fetchRecoveryCodes={fetchRecoveryCodes}
              recoveryCodesList={recoveryCodesList}
            />
          ) : (
            <TwoFactorDevices />
          )}
        </CardContent>
      </Card>

      {renderSecurityDialog()}

      <TwoFactorActivationDetailsDialog
        confirmedAt={twoFactorConfirmedAt}
        isOpen={activationDetailsDialog.open}
        setOpen={activationDetailsDialog.setOpen}
      />
    </div>
  );
}

interface TwoFactorTitleProps {
  onManageAction: (action: TwoFactorManageActionKey) => void;
}

function TwoFactorTitle({ onManageAction }: TwoFactorTitleProps): JSX.Element {
  const { t } = useTranslation();

  return (
    <div className="flex min-w-0 flex-col items-start gap-4 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:gap-6 sm:p-6">
      <div className={cn(iconColorVariants.green.iconBgClass, "rounded-3xl p-2")}>
        <ShieldCheck className={cn("h-12 w-12", iconColorVariants.green.iconFgClass)} />
      </div>

      <div className="flex min-w-0 flex-1 flex-col gap-3">
        <div className="flex flex-col gap-1">
          <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
            {t("setting/two-factor.enabled_view.active_title")}
          </h1>

          <p className="text-sm text-muted-foreground">
            {t("setting/two-factor.enabled_view.active_description")}
          </p>
        </div>

        <div className="mt-2 flex items-center gap-2 sm:mt-4">
          <ClipboardCheck className="h-4 w-4 text-green-700 dark:text-green-500" />

          <span className="text-sm text-muted-foreground">
            <strong className="font-medium">{t("setting/two-factor.enabled_view.method")} </strong>
            {t("setting/two-factor.labels.method_totp")}
          </span>
        </div>
      </div>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button className="w-full sm:w-auto" variant="outline">
            <Bolt />
            {t("setting/two-factor.enabled_view.manage")}
            <ChevronDown />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuLabel>{t("setting/two-factor.enabled_view.manage_menu")}</DropdownMenuLabel>

          {twoFactorManageActions.map((group, groupIndex) => (
            <Fragment key={groupIndex}>
              <DropdownMenuGroup>
                {group.label && <DropdownMenuLabel>{t(group.label)}</DropdownMenuLabel>}

                {group.actions.map((action) => {
                  const Icon = action.icon;

                  return (
                    <DropdownMenuItem
                      key={action.key}
                      onClick={() => {
                        onManageAction(action.key);
                      }}
                      className={action.className}
                    >
                      <Icon className={action.iconClassName} />
                      {t(action.label)}
                    </DropdownMenuItem>
                  );
                })}
              </DropdownMenuGroup>

              {groupIndex < twoFactorManageActions.length - 1 && <DropdownMenuSeparator />}
            </Fragment>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}

interface TwoFactorSecuritySummaryProps {
  recoveryCodesList: string[];
  onViewTrustedDevices: () => void;
  onActivationDetailsClick: () => void;
  twoFactorConfirmedAt: string | null;
}

type TwoFactorSecuritySummaryPageProps = SharedData & {
  trustedDevicesCount?: number;
};

function TwoFactorSecuritySummary({
  recoveryCodesList,
  onViewTrustedDevices,
  onActivationDetailsClick,
  twoFactorConfirmedAt,
}: TwoFactorSecuritySummaryProps): JSX.Element {
  const { t, tChoice } = useTranslation();
  const { formatLongDate } = useDateTime();
  const { trustedDevicesCount = 0 } = usePage<TwoFactorSecuritySummaryPageProps>().props;

  const trustedDevicesLabel = tChoice(
    "setting/two-factor.summary.trusted_devices_count",
    trustedDevicesCount,
  );

  const activationDescription =
    twoFactorConfirmedAt === null
      ? t("setting/two-factor.summary.not_activated")
      : t("setting/two-factor.summary.activated_on", {
          date: formatLongDate(twoFactorConfirmedAt),
        });

  const summarySecurity: SumaryCardItem[] = [
    {
      key: "status-2fa",
      title: t("setting/two-factor.summary.status_title"),
      description: t("setting/two-factor.summary.status_description"),
      icon: ShieldCheck,
      iconColor: "green",
      action: {
        type: "badge",
        variant: "default",
        label: t("setting/two-factor.summary.status_active"),
      },
    },
    {
      key: "trusted-devices",
      title: t("setting/two-factor.labels.trusted_devices"),
      description: trustedDevicesLabel,
      icon: MonitorSmartphone,
      iconColor: "violet",
      action: {
        type: "button",
        label:
          trustedDevicesCount === 0
            ? t("setting/two-factor.summary.configure")
            : t("setting/two-factor.summary.view_devices"),
        onClick: onViewTrustedDevices,
      },
    },
    {
      key: "backup-codes",
      title: t("setting/two-factor.labels.backup_codes"),
      description: t("setting/two-factor.summary.backup_codes_available", {
        available: recoveryCodesList.length,
        total: RECOVERY_CODES_TOTAL,
      }),
      icon: Key,
      iconColor: "orange",
      action: {
        type: "progress",
        current: recoveryCodesList.length,
        total: RECOVERY_CODES_TOTAL,
      },
    },
    {
      key: "activation-date",
      title: t("setting/two-factor.labels.activation_date"),
      description: activationDescription,
      icon: Clock4,
      iconColor: "blue",
      action: {
        type: "chevron",
        onClick: onActivationDetailsClick,
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
          <Button
            onClick={action.onClick}
            className="rounded-full"
            size="icon"
            type="button"
            aria-label={t("setting/two-factor.enabled_view.view_details")}
            variant="ghost"
          >
            <ChevronRight className="size-5 text-muted-foreground" />
          </Button>
        );
      case "none":
        return null;
    }
  };

  return (
    <SummaryCard
      title={t("setting/two-factor.enabled_view.summary_title")}
      data={summarySecurity}
      showLastSeparator
      renderAction={renderAction}
    />
  );
}

function TwoFactorSafetyTips(): JSX.Element {
  const { t } = useTranslation();

  return (
    <Card className="shadow-xs">
      <CardHeader>
        <h2 className="flex items-center text-lg leading-none font-semibold">
          <Info className="mr-2 h-6 w-6 text-purple-700 dark:text-purple-500" />
          {t("setting/two-factor.enabled_view.safety_tips_title")}
        </h2>
      </CardHeader>
      <CardContent className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
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
                <h3 className="text-sm font-medium">{t(tip.title)}</h3>

                <p className="text-xs text-muted-foreground">{t(tip.description)}</p>
              </div>
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}

export default TwoFactorEnable;
