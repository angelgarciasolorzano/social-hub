import type { JSX } from "react";
import { Fragment, useCallback, useEffect } from "react";

import { usePage } from "@inertiajs/react";

import { AlertTriangleIcon, ArrowDown, Check, Clock4, Copy } from "lucide-react";

import AlertError from "@/shared/components/AlertError";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemTitle,
} from "@/shared/components/shadcn/ui/item";
import { Separator } from "@/shared/components/shadcn/ui/separator";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useClipboard } from "@/shared/hooks/useClipboard";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

import { downloadRecoveryCodes } from "../../../utils/downloadRecoveryCodes";

interface TwoFactorRecoveryCodesProps {
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  recoveryCodesList: string[];
}

function TwoFactorRecoveryCodes(props: TwoFactorRecoveryCodesProps): JSX.Element {
  const { errors, fetchRecoveryCodes, recoveryCodesList } = props;
  const { email: accountEmail } = usePage<SharedData>().props.auth.user;

  const [copiedText, copy] = useClipboard({ resetTimeout: 2000 });

  const handleCopy = useCallback((): void => {
    if (!recoveryCodesList.length) {
      return;
    }

    void copy(recoveryCodesList.join("\n"));
  }, [recoveryCodesList, copy]);

  const handleDownload = useCallback((): void => {
    downloadRecoveryCodes(recoveryCodesList, { accountEmail });
  }, [recoveryCodesList, accountEmail]);

  useEffect(() => {
    if (!recoveryCodesList.length) {
      void fetchRecoveryCodes();
    }
  }, [recoveryCodesList.length, fetchRecoveryCodes]);

  return (
    <>
      {errors.length > 0 ? (
        <AlertError errors={errors} title="No se pudieron cargar los códigos de respaldo." />
      ) : (
        <Alert className={cn(alertVariants.info, "max-w-md")}>
          <AlertTriangleIcon />

          <AlertTitle>Guarda estos códigos en un lugar seguro.</AlertTitle>

          <AlertDescription>
            Te permitirán acceder a tu cuenta si pierdes el acceso a tu aplicación autenticadora.
          </AlertDescription>
        </Alert>
      )}

      <div className="flex flex-col gap-3 rounded-md border p-5">
        {recoveryCodesList.length ? (
          <>
            {recoveryCodesList.map((code, index) => {
              return (
                <Fragment key={index}>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">{code}</span>

                    <Copy size={16} className="text-xs text-muted-foreground" />
                  </div>

                  {index < recoveryCodesList.length - 1 && <Separator />}
                </Fragment>
              );
            })}
          </>
        ) : (
          <div aria-label="Cargando códigos de respaldo" className="space-y-2">
            {Array.from({ length: 8 }, (_, index) => (
              <Skeleton key={index} className="h-5 w-full" />
            ))}
          </div>
        )}
      </div>

      <div className="flex items-center justify-between text-sm">
        <span className="text-muted-foreground">Códigos disponibles</span>

        <span className="font-medium">{recoveryCodesList.length} de 8</span>
      </div>

      <div className="flex items-center justify-between gap-4">
        <Button
          variant="outline"
          className="flex-1 py-6"
          disabled={!recoveryCodesList.length}
          onClick={handleCopy}
        >
          {copiedText !== null ? (
            <>
              <Check className="h-4 w-4 text-green-600 dark:text-green-500" />
              Copiado
            </>
          ) : (
            <>
              <Copy className="h-4 w-4" />
              Copiar Códigos
            </>
          )}
        </Button>

        <Button
          className="flex-1 py-6"
          disabled={!recoveryCodesList.length}
          onClick={handleDownload}
        >
          <ArrowDown />
          Descargar .txt
        </Button>
      </div>

      <Item variant="outline">
        <ItemContent>
          <ItemTitle>Última regeneración</ItemTitle>

          <ItemDescription>20 Mayo 2024, 14:30 PM</ItemDescription>
        </ItemContent>

        <ItemActions>
          <Clock4 size={20} className="text-muted-foreground" />
        </ItemActions>
      </Item>
    </>
  );
}

export default TwoFactorRecoveryCodes;
