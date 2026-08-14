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

import { formatLongDate, fromNow } from "../../../utils/dateTime";
import { downloadRecoveryCodes } from "../../../utils/downloadRecoveryCodes";

interface TwoFactorRecoveryCodesProps {
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  recoveryCodesList: string[];
}

type TwoFactorRecoveryCodesPageProps = SharedData & {
  recoveryCodesRegeneratedAt?: string | null;
};

function TwoFactorRecoveryCodes(props: TwoFactorRecoveryCodesProps): JSX.Element {
  const { errors, fetchRecoveryCodes, recoveryCodesList } = props;

  const { recoveryCodesRegeneratedAt = null, auth } =
    usePage<TwoFactorRecoveryCodesPageProps>().props;
  const { email: accountEmail } = auth.user;

  const regeneratedAtLabel =
    recoveryCodesRegeneratedAt === null
      ? fromNow(null)
      : formatLongDate(recoveryCodesRegeneratedAt);

  const [bulkCopiedText, copyBulk] = useClipboard({ resetTimeout: 2000 });
  const [rowCopiedText, copyRow] = useClipboard({ resetTimeout: 2000 });

  const handleCopy = useCallback((): void => {
    if (!recoveryCodesList.length) {
      return;
    }

    void copyBulk(recoveryCodesList.join("\n"));
  }, [recoveryCodesList, copyBulk]);

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
              const isCopied = rowCopiedText === code;

              return (
                <Fragment key={index}>
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={() => {
                      void copyRow(code);
                    }}
                    aria-label={isCopied ? `Código ${code} copiado` : `Copiar código ${code}`}
                    className="-mx-2 -my-1.5 h-auto w-full justify-between rounded-md px-2 py-1.5 text-sm"
                  >
                    <span className="font-medium">{code}</span>

                    {isCopied ? (
                      <Check size={16} className="text-xs text-green-600 dark:text-green-500" />
                    ) : (
                      <Copy size={16} className="text-xs text-muted-foreground" />
                    )}
                  </Button>

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
          {bulkCopiedText !== null ? (
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

          <ItemDescription>{regeneratedAtLabel}</ItemDescription>
        </ItemContent>

        <ItemActions>
          <Clock4 size={20} className="text-muted-foreground" />
        </ItemActions>
      </Item>
    </>
  );
}

export default TwoFactorRecoveryCodes;
