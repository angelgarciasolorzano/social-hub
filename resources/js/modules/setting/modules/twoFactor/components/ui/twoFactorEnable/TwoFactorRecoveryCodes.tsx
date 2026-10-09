import type { JSX } from "react";
import { useCallback, useEffect, useRef, useState } from "react";

import { usePage } from "@inertiajs/react";

import { AlertTriangleIcon, ArrowDown, Check, Clock4, Copy, RotateCcw } from "lucide-react";

import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";

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
  const [isLoadingRecoveryCodes, setIsLoadingRecoveryCodes] = useState<boolean>(
    recoveryCodesList.length === 0,
  );
  const hasStartedInitialRecoveryCodesFetch = useRef<boolean>(false);

  const handleCopy = useCallback<() => void>(() => {
    if (!recoveryCodesList.length) {
      return;
    }

    void copyBulk(recoveryCodesList.join("\n"));
  }, [recoveryCodesList, copyBulk]);

  const handleDownload = useCallback<() => void>(() => {
    downloadRecoveryCodes(recoveryCodesList, { accountEmail });
  }, [recoveryCodesList, accountEmail]);

  const loadRecoveryCodes = useCallback<() => Promise<void>>(async () => {
    setIsLoadingRecoveryCodes(true);

    try {
      await fetchRecoveryCodes();
    } finally {
      setIsLoadingRecoveryCodes(false);
    }
  }, [fetchRecoveryCodes]);

  const handleRetry = useCallback<() => void>(() => {
    void loadRecoveryCodes();
  }, [loadRecoveryCodes]);

  useEffect((): void => {
    if (recoveryCodesList.length > 0) {
      hasStartedInitialRecoveryCodesFetch.current = false;

      return;
    }

    if (hasStartedInitialRecoveryCodesFetch.current) {
      return;
    }

    hasStartedInitialRecoveryCodesFetch.current = true;
    void loadRecoveryCodes();
  }, [loadRecoveryCodes, recoveryCodesList.length]);

  if (isLoadingRecoveryCodes) {
    return <TwoFactorRecoveryCodesSkeleton />;
  }

  return (
    <>
      {errors.length > 0 ? (
        <AlertError errors={errors} title="No se pudieron cargar los códigos de respaldo." />
      ) : recoveryCodesList.length > 0 ? (
        <Alert className={cn(alertVariants.info, "max-w-md")} role="note">
          <AlertTriangleIcon aria-hidden="true" />

          <AlertTitle>Guarda estos códigos en un lugar seguro.</AlertTitle>

          <AlertDescription>
            Te permitirán acceder a tu cuenta si pierdes el acceso a tu aplicación autenticadora.
          </AlertDescription>
        </Alert>
      ) : null}

      <div className="flex flex-col gap-3 rounded-md border p-5">
        {recoveryCodesList.length > 0 ? (
          <ul aria-label="Códigos de respaldo" className="flex flex-col" role="list">
            {recoveryCodesList.map((code, codeIndex) => {
              const isCopied = rowCopiedText === code;

              return (
                <li key={code}>
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={() => {
                      void copyRow(code);
                    }}
                    aria-label={
                      isCopied
                        ? `Código de respaldo ${code} copiado`
                        : `Copiar código de respaldo ${code}`
                    }
                    className="h-auto w-full justify-between rounded-md px-0 py-3 text-sm hover:bg-accent/50 focus-visible:bg-accent/50"
                  >
                    <span className="font-medium">{code}</span>

                    {isCopied ? (
                      <Check
                        aria-hidden="true"
                        size={16}
                        className="text-xs text-green-600 dark:text-green-500"
                      />
                    ) : (
                      <Copy
                        aria-hidden="true"
                        size={16}
                        className="text-xs text-muted-foreground"
                      />
                    )}
                  </Button>

                  {codeIndex < recoveryCodesList.length - 1 && <Separator aria-hidden="true" />}
                </li>
              );
            })}
          </ul>
        ) : (
          <div className="flex flex-col items-center gap-3 py-4 text-center">
            <p
              className="text-sm text-muted-foreground"
              role={errors.length === 0 ? "status" : undefined}
            >
              {errors.length > 0
                ? "Intenta cargar los códigos de respaldo otra vez."
                : "No hay códigos de respaldo disponibles."}
            </p>

            <Button onClick={handleRetry} type="button" variant="outline">
              <RotateCcw aria-hidden="true" />
              {errors.length > 0 ? "Reintentar" : "Cargar códigos"}
            </Button>
          </div>
        )}
      </div>

      <div className="flex items-center justify-between">
        <span className="text-xs text-muted-foreground">Códigos disponibles</span>

        <span className="text-sm font-medium">{recoveryCodesList.length} de 8</span>
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
              <Check aria-hidden="true" className="h-4 w-4 text-green-600 dark:text-green-500" />
              Copiado
            </>
          ) : (
            <>
              <Copy aria-hidden="true" className="h-4 w-4" />
              Copiar Códigos
            </>
          )}
        </Button>

        <Button
          className="flex-1 py-6"
          disabled={!recoveryCodesList.length}
          onClick={handleDownload}
        >
          <ArrowDown aria-hidden="true" />
          Descargar .txt
        </Button>
      </div>

      <Item variant="outline">
        <ItemContent>
          <ItemTitle>Última regeneración</ItemTitle>

          <ItemDescription className="text-xs">{regeneratedAtLabel}</ItemDescription>
        </ItemContent>

        <ItemActions>
          <Clock4 aria-hidden="true" size={20} className="text-muted-foreground" />
        </ItemActions>
      </Item>
    </>
  );
}

function TwoFactorRecoveryCodesSkeleton(): JSX.Element {
  const recoveryCodeSkeletonRows: string[] = [
    "first",
    "second",
    "third",
    "fourth",
    "fifth",
    "sixth",
    "seventh",
    "eighth",
  ];

  return (
    <>
      <p className="sr-only" role="status">
        Cargando códigos de respaldo.
      </p>

      <Alert aria-hidden="true" className="max-w-md">
        <Skeleton className="size-4 rounded-full" />

        <AlertTitle>
          <Skeleton className="h-4 w-4/5" />
        </AlertTitle>

        <AlertDescription className="flex flex-col gap-2">
          <Skeleton className="h-3 w-full" />
          <Skeleton className="h-3 w-3/4" />
        </AlertDescription>
      </Alert>

      <div aria-hidden="true" className="flex flex-col gap-3 rounded-md border p-5">
        <div className="flex flex-col">
          {recoveryCodeSkeletonRows.map((recoveryCodeSkeletonRow, skeletonIndex) => (
            <div key={recoveryCodeSkeletonRow}>
              <div className="py-3">
                <Skeleton className="h-5 w-full" />
              </div>

              {skeletonIndex < recoveryCodeSkeletonRows.length - 1 && <Separator />}
            </div>
          ))}
        </div>
      </div>

      <div aria-hidden="true" className="flex items-center justify-between">
        <Skeleton className="h-3 w-24" />
        <Skeleton className="h-4 w-12" />
      </div>

      <div aria-hidden="true" className="flex items-center justify-between gap-4">
        <Skeleton className="h-12 flex-1" />
        <Skeleton className="h-12 flex-1" />
      </div>

      <Item aria-hidden="true" variant="outline">
        <ItemContent>
          <ItemTitle>
            <Skeleton className="h-4 w-36" />
          </ItemTitle>

          <ItemDescription className="text-xs">
            <Skeleton className="h-3 w-20" />
          </ItemDescription>
        </ItemContent>

        <ItemActions>
          <Skeleton className="size-5 rounded-full" />
        </ItemActions>
      </Item>
    </>
  );
}

export default TwoFactorRecoveryCodes;
