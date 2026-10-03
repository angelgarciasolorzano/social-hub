import type { JSX } from "react";

import { Head, usePage } from "@inertiajs/react";

import { ShieldCheck, UserRound } from "lucide-react";

import AccountActionsPanel from "@/modules/setting/modules/accountSettings/components/AccountActionsPanel";
import AccountSettingsProfileForm from "@/modules/setting/modules/accountSettings/components/AccountSettingsProfileForm";
import AccountSettingsSummaryCard from "@/modules/setting/modules/accountSettings/components/AccountSettingsSummaryCard";
import {
  communicationChannels,
  privacyPrinciples,
} from "@/modules/setting/modules/accountSettings/data/accountSettingsSections";
import type { AccountSettingsPageProps } from "@/modules/setting/modules/accountSettings/types/accountSettings";

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
import { Switch } from "@/shared/components/shadcn/ui/switch";

import { cn } from "@/shared/lib";
import { iconColorVariants } from "@/shared/lib/styling";

function AccountSettings(): JSX.Element {
  const { accountSettings } = usePage<AccountSettingsPageProps>().props;

  return (
    <main className="max-w-8xl mx-auto flex w-full flex-1 flex-col gap-6">
      <Head title="Configuración de la cuenta" />

      <div className="flex min-w-0 flex-col gap-6 xl:flex-row">
        <div className="flex min-w-0 flex-1 flex-col gap-6">
          <AccountSettingsPrivacyOverview />

          <section aria-labelledby="account-profile-heading" className="min-w-0">
            <div className="space-y-6">
              <AccountSettingsProfileForm accountSettings={accountSettings} />
              <AccountCommunicationPreferences />
            </div>
          </section>
        </div>

        <aside className="flex w-full min-w-0 flex-col gap-6 xl:max-w-sm xl:shrink-0 xl:self-start">
          <AccountSettingsSummaryCard accountSettings={accountSettings} />
          <AccountActionsPanel userId={accountSettings.id} />
        </aside>
      </div>
    </main>
  );
}

function AccountSettingsPrivacyOverview(): JSX.Element {
  return (
    <Card className="gap-0 overflow-hidden py-0">
      <CardHeader className="flex grid-cols-1 grid-rows-none items-start gap-4 px-5 pt-5 pb-4 sm:px-6 sm:pt-6">
        <span
          aria-hidden="true"
          className={cn(
            "flex size-12 shrink-0 items-center justify-center rounded-2xl",
            iconColorVariants.violet.iconBgClass,
          )}
        >
          <UserRound className={cn("size-6", iconColorVariants.violet.iconFgClass)} />
        </span>

        <div className="min-w-0 space-y-1">
          <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
            Información de tu perfil
          </h1>
          <CardDescription>
            Administra la información personal asociada a tu cuenta.
          </CardDescription>
          <p className="text-sm text-muted-foreground">
            Mantén tus datos actualizados para garantizar una mejor experiencia.
          </p>
        </div>
      </CardHeader>

      <CardContent className="space-y-5 px-5 pt-0 pb-5 sm:px-6 sm:pb-6">
        <div className="flex items-start gap-3 rounded-lg border border-violet-200/70 bg-violet-50/80 p-4 sm:p-5 dark:border-violet-900/50 dark:bg-violet-950/30">
          <span
            aria-hidden="true"
            className={cn(
              "flex size-10 shrink-0 items-center justify-center rounded-full",
              iconColorVariants.violet.iconBgClass,
            )}
          >
            <ShieldCheck className={cn("size-5", iconColorVariants.violet.iconFgClass)} />
          </span>

          <div className="min-w-0 space-y-1">
            <h2
              className={cn("font-semibold tracking-tight", iconColorVariants.violet.iconFgClass)}
            >
              Tu información es privada y segura
            </h2>
            <p className="text-sm text-muted-foreground">
              Solo tú puedes ver y modificar estos datos. Nunca compartiremos tu información
              personal.
            </p>
          </div>
        </div>

        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {privacyPrinciples.map(({ description, icon: Icon, iconColor, title }) => (
            <li className="flex min-w-0 items-start gap-3" key={title}>
              <span
                aria-hidden="true"
                className={cn(
                  "flex size-9 shrink-0 items-center justify-center rounded-full",
                  iconColorVariants[iconColor].iconBgClass,
                )}
              >
                <Icon className={cn("size-4", iconColorVariants[iconColor].iconFgClass)} />
              </span>

              <div className="min-w-0 space-y-1">
                <h3 className="text-sm leading-5 font-medium">{title}</h3>
                <p className="text-xs leading-5 text-muted-foreground">{description}</p>
              </div>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

function AccountCommunicationPreferences(): JSX.Element {
  return (
    <Card className="overflow-hidden py-0">
      <CardHeader className="px-5 pt-5 sm:px-6 sm:pt-6">
        <CardTitle>Preferencias de comunicación</CardTitle>
        <CardDescription>
          Ejemplo visual de los tipos de comunicación disponibles en la plataforma.
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-4 px-5 pb-5 sm:px-6 sm:pb-6">
        {communicationChannels.map(({ description, enabled, icon: Icon, iconColor, id, title }) => (
          <div className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3" key={id}>
            <span
              aria-hidden="true"
              className={cn(
                "flex size-10 shrink-0 items-center justify-center rounded-lg",
                iconColorVariants[iconColor].iconBgClass,
              )}
            >
              <Icon className={cn("size-4.5", iconColorVariants[iconColor].iconFgClass)} />
            </span>

            <div className="min-w-0 space-y-0.5">
              <h3 className="text-sm font-medium" id={`account-communication-channel-${id}`}>
                {title}
              </h3>
              <p className="text-xs leading-5 text-muted-foreground">{description}</p>
            </div>

            <Switch
              aria-labelledby={`account-communication-channel-${id}`}
              checked={enabled}
              disabled
            />
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

export default AccountSettings;
