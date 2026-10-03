import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { ChevronRight, ExternalLink, Eye, KeyRound, LockKeyhole, Trash2 } from "lucide-react";

import DeleteAccountDialog from "@/modules/setting/modules/accountSettings/components/DeleteAccountDialog";

import { edit as editPassword } from "@/shared/wayfinder/actions/App/Auth/Modules/Password/Controllers/PasswordController";

import { index as publicProfileIndex } from "@/shared/wayfinder/routes/profile";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader } from "@/shared/components/shadcn/ui/card";

interface AccountActionsPanelProps {
  userId: number;
}

function AccountActionsPanel({ userId }: AccountActionsPanelProps): JSX.Element {
  return (
    <div className="flex w-full min-w-0 flex-col gap-6">
      <Card className="gap-4 overflow-hidden py-0">
        <CardHeader className="flex grid-cols-1 grid-rows-none flex-row items-start gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
          <span
            aria-hidden="true"
            className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-foreground"
          >
            <Eye className="size-5" />
          </span>

          <div className="min-w-0 space-y-1">
            <h2 className="font-semibold tracking-tight">Visibilidad del perfil</h2>
          </div>
        </CardHeader>

        <CardContent className="space-y-4 px-5 pb-5 sm:px-6 sm:pb-6">
          <p className="text-sm leading-6 text-muted-foreground">
            Controla quién puede ver tu perfil público y qué información se muestra.
          </p>

          <Button asChild className="max-w-full justify-between" size="sm" variant="outline">
            <Link href={publicProfileIndex.url()}>
              <span className="inline-flex min-w-0 items-center gap-2">
                <ExternalLink aria-hidden="true" className="size-4 shrink-0" />
                Ir a mi perfil público
              </span>
              <ChevronRight aria-hidden="true" className="size-4 shrink-0" />
            </Link>
          </Button>
        </CardContent>
      </Card>

      <Card className="gap-4 overflow-hidden py-0">
        <CardHeader className="flex grid-cols-1 grid-rows-none flex-row items-start gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
          <span
            aria-hidden="true"
            className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-foreground"
          >
            <LockKeyhole className="size-5" />
          </span>

          <div className="min-w-0 space-y-1">
            <h2 className="font-semibold tracking-tight">Cambiar contraseña</h2>
          </div>
        </CardHeader>

        <CardContent className="space-y-4 px-5 pb-5 sm:px-6 sm:pb-6">
          <p className="text-sm leading-6 text-muted-foreground">
            Elige una contraseña segura que no uses en otros sitios.
          </p>

          <Button asChild className="max-w-full justify-between" size="sm" variant="outline">
            <Link href={editPassword.url()}>
              <span className="inline-flex min-w-0 items-center gap-2">
                <KeyRound aria-hidden="true" className="size-4 shrink-0" />
                Cambiar contraseña
              </span>
              <ChevronRight aria-hidden="true" className="size-4 shrink-0" />
            </Link>
          </Button>
        </CardContent>
      </Card>

      <Card className="gap-4 overflow-hidden border-destructive/30 py-0">
        <CardHeader className="flex grid-cols-1 grid-rows-none flex-row items-start gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
          <span
            aria-hidden="true"
            className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-destructive/10 text-destructive"
          >
            <Trash2 className="size-5" />
          </span>

          <div className="min-w-0 space-y-1">
            <h2 className="font-semibold tracking-tight text-destructive">Eliminar cuenta</h2>
          </div>
        </CardHeader>

        <CardContent className="space-y-4 px-5 pb-5 sm:px-6 sm:pb-6">
          <p className="text-sm leading-6 text-destructive">
            Esta acción es permanente. Se eliminarán los datos de tu cuenta.
          </p>

          <DeleteAccountDialog userId={userId} />
        </CardContent>
      </Card>
    </div>
  );
}

export default AccountActionsPanel;
