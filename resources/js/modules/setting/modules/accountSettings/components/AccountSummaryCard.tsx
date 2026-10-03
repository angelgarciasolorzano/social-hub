import type { JSX } from "react";

import { CalendarDays, Clock, Mail } from "lucide-react";

import type { AccountSettings } from "@/modules/setting/modules/accountSettings/types/accountSettings";
import { formatActivationDate, formatLongDate } from "@/modules/setting/shared/utils/dateTime";

import { Avatar, AvatarFallback } from "@/shared/components/shadcn/ui/avatar";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";

interface AccountSummaryCardProps {
  accountSettings: AccountSettings;
}

function AccountSummaryCard({ accountSettings }: AccountSummaryCardProps): JSX.Element {
  const userInitial = accountSettings.name.trim().charAt(0).toLocaleUpperCase("es");

  return (
    <Card className="gap-0 overflow-hidden py-0">
      <CardHeader className="border-b px-5 py-5 sm:px-6">
        <CardTitle>Resumen de la cuenta</CardTitle>
        <CardDescription>Información de tu perfil.</CardDescription>
      </CardHeader>

      <CardContent className="space-y-5 px-5 py-5 sm:px-6">
        <div className="flex min-w-0 items-center gap-3">
          <Avatar aria-hidden="true" className="size-11" size="lg">
            <AvatarFallback className="text-base font-semibold">{userInitial}</AvatarFallback>
          </Avatar>

          <dl className="min-w-0">
            <dt className="text-xs font-medium text-muted-foreground">Usuario</dt>
            <dd className="truncate font-medium" title={accountSettings.name}>
              {accountSettings.name}
            </dd>
          </dl>
        </div>

        <dl className="space-y-4 border-t pt-4">
          <div className="min-w-0 space-y-1">
            <dt className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
              <Mail aria-hidden="true" className="shrink-0" size={14} />
              Correo electrónico
            </dt>
            <dd className="text-sm break-all">{accountSettings.email}</dd>
          </div>

          <div className="space-y-1">
            <dt className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
              <CalendarDays aria-hidden="true" className="shrink-0" size={14} />
              Miembro desde
            </dt>
            <dd className="text-sm">
              <time dateTime={accountSettings.createdAt ?? undefined}>
                {formatActivationDate(accountSettings.createdAt)}
              </time>
            </dd>
          </div>

          <div className="space-y-1">
            <dt className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
              <Clock aria-hidden="true" className="shrink-0" size={14} />
              Último acceso
            </dt>
            <dd className="text-sm">
              <time dateTime={accountSettings.lastLoginAt ?? undefined}>
                {formatLongDate(accountSettings.lastLoginAt)}
              </time>
            </dd>
          </div>
        </dl>
      </CardContent>
    </Card>
  );
}

export default AccountSummaryCard;
