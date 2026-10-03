import type { JSX } from "react";

import { Head, usePage } from "@inertiajs/react";

import { ShieldCheck } from "lucide-react";

import AccountProfileForm from "@/modules/setting/modules/accountSettings/components/AccountProfileForm";
import AccountSummaryCard from "@/modules/setting/modules/accountSettings/components/AccountSummaryCard";
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

function AccountSettings(): JSX.Element {
  const { accountSettings } = usePage<AccountSettingsPageProps>().props;

  return (
    <main className="max-w-8xl mx-auto flex w-full flex-1 flex-col gap-6">
      <Head title="Configuración de la cuenta" />

      <header className="space-y-2">
        <p className="text-sm font-medium text-muted-foreground">Cuenta</p>
        <h1 className="text-2xl font-semibold tracking-tight">Configuración de la cuenta</h1>
        <p className="max-w-2xl text-sm text-muted-foreground">
          Administra la información personal asociada a tu cuenta.
        </p>
      </header>

      <div className="grid min-w-0 flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="account-profile-heading" className="min-w-0">
          <div className="space-y-6">
            <AccountPrivacyNotice />
            <AccountProfileForm accountSettings={accountSettings} />
            <AccountCommunicationPreferences />
          </div>
        </section>

        <aside aria-label="Resumen de la cuenta" className="min-w-0">
          <AccountSummaryCard accountSettings={accountSettings} />
        </aside>
      </div>
    </main>
  );
}

function AccountPrivacyNotice(): JSX.Element {
  return (
    <Card className="gap-0 overflow-hidden py-0">
      <CardContent className="space-y-5 p-5 sm:p-6">
        <div className="flex items-start gap-3 rounded-lg border border-primary/10 bg-primary/5 p-4 sm:p-5">
          <span
            aria-hidden="true"
            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
          >
            <ShieldCheck size={20} />
          </span>

          <div className="min-w-0 space-y-1">
            <h2 className="font-semibold tracking-tight">Tu información es privada y segura</h2>
            <p className="text-sm text-muted-foreground">
              Solo tú puedes ver y modificar estos datos. Nunca compartiremos tu información
              personal.
            </p>
          </div>
        </div>

        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {privacyPrinciples.map(({ description, icon: Icon, title }) => (
            <li className="flex min-w-0 items-start gap-3" key={title}>
              <span
                aria-hidden="true"
                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
              >
                <Icon size={16} />
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
        {communicationChannels.map(({ description, enabled, icon: Icon, id, title }) => (
          <div className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3" key={id}>
            <span
              aria-hidden="true"
              className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
              <Icon size={18} />
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
