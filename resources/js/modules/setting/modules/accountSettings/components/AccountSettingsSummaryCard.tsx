import type { JSX } from "react";

import { Activity, CalendarDays, Clock, Mail, ShieldCheck } from "lucide-react";

import type {
  AccountSettings,
  AccountStatus,
} from "@/modules/setting/modules/accountSettings/types/accountSettings";

import { Avatar, AvatarFallback } from "@/shared/components/shadcn/ui/avatar";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";

import { formatActivationDate, formatLongDate } from "@/shared/lib/dateTime";
import { badgeVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

const accountStatusBadges: Record<AccountStatus, { className: string; label: string }> = {
  active: { className: badgeVariants.success, label: "Activa" },
  suspended: { className: badgeVariants.red, label: "Suspendida" },
};

interface AccountSettingsSummaryCardProps {
  accountSettings: AccountSettings;
}

function AccountSettingsSummaryCard({
  accountSettings,
}: AccountSettingsSummaryCardProps): JSX.Element {
  const userInitial = accountSettings.name.trim().charAt(0).toLocaleUpperCase("es");
  const accountStatusBadge = accountStatusBadges[accountSettings.status];

  return (
    <Card className="gap-0 overflow-hidden py-0">
      <CardHeader className="flex flex-col gap-3 border-b px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div className="min-w-0 space-y-1">
          <CardTitle className="sm:whitespace-nowrap">Resumen de la cuenta</CardTitle>
          <CardDescription>Información de tu perfil.</CardDescription>
        </div>

        <Button className="shrink-0" disabled size="sm" type="button" variant="outline">
          <Activity aria-hidden="true" data-icon="inline-start" />
          Ver actividad
        </Button>
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
              <ShieldCheck aria-hidden="true" className="shrink-0" size={14} />
              Estado de la cuenta
            </dt>
            <dd>
              <Badge className={cn(accountStatusBadge.className, "font-normal")}>
                {accountStatusBadge.label}
              </Badge>
            </dd>
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

export default AccountSettingsSummaryCard;
