import { Fragment, useEffect } from "react";

import { AlertTriangleIcon, ArrowDown, Clock4, Copy } from "lucide-react";

import AlertError from "@/shared/components/AlertError";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/ui/alert";
import { Button } from "@/shared/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/ui/card";
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemTitle,
} from "@/shared/components/ui/item";
import { Separator } from "@/shared/components/ui/separator";
import { Skeleton } from "@/shared/components/ui/skeleton";

interface TwoFactorRecoveryCodesProps {
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  recoveryCodesList: string[];
}

function TwoFactorBackupCodes(props: TwoFactorRecoveryCodesProps) {
  const { errors, fetchRecoveryCodes, recoveryCodesList } = props;

  useEffect(() => {
    if (!recoveryCodesList.length) {
      fetchRecoveryCodes();
    }
  }, [recoveryCodesList.length, fetchRecoveryCodes]);

  return (
    <Card className="min-w-105">
      <CardHeader>
        <CardTitle>Códigos de respaldo</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        {errors?.length > 0 ? (
          <AlertError errors={errors} title="No se pudieron cargar los códigos de respaldo." />
        ) : (
          <Alert className="max-w-md border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
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
            <div aria-label="Loading recovery codes" className="space-y-2">
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
          <Button variant="outline" className="flex-1 py-6">
            <Copy className="mr-2 h-4 w-4" />
            Copiar Códigos
          </Button>

          <Button className="flex-1 py-6">
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
      </CardContent>
    </Card>
  );
}

export default TwoFactorBackupCodes;
