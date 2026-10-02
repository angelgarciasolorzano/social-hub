import type { JSX } from "react";

import { Head, usePage } from "@inertiajs/react";

import AccountSummaryCard from "@/modules/setting/modules/accountSettings/components/AccountSummaryCard";
import type { AccountSettingsPageProps } from "@/modules/setting/modules/accountSettings/types/accountSettings";

function AccountSettings(): JSX.Element {
  const { accountSettings } = usePage<AccountSettingsPageProps>().props;

  return (
    <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6">
      <Head title="Configuración de la cuenta" />

      <header className="space-y-2">
        <p className="text-sm font-medium text-muted-foreground">Cuenta</p>
        <h1 className="text-2xl font-semibold tracking-tight">Configuración de la cuenta</h1>
        <p className="max-w-2xl text-sm text-muted-foreground">
          Administra la información personal asociada a tu cuenta.
        </p>
      </header>

      <div className="grid min-w-0 flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="account-profile-heading" className="min-w-0 space-y-2">
          <h2 className="text-lg font-semibold" id="account-profile-heading">
            Información personal
          </h2>
          <p className="text-sm text-muted-foreground">
            Actualiza los datos personales que forman parte de tu perfil.
          </p>
        </section>

        <aside aria-label="Resumen de la cuenta" className="min-w-0">
          <AccountSummaryCard accountSettings={accountSettings} />
        </aside>
      </div>
    </main>
  );
}

export default AccountSettings;
