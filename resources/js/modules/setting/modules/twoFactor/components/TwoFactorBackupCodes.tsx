import { Fragment } from "react";

import { AlertTriangleIcon, ArrowDown, Clock4, Copy } from "lucide-react";

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

function TwoFactorBackupCodes() {
  const backupCodes = [
    "8F3A - 701B",
    "2C9E - 4A7F",
    "6B2D - 9E1C",
    "D4F8 - 3A6B",
    "7E1C - 5D9A",
    "3B7E - 6C2D",
    "1F9A - 8B3E",
    "5C2D - 7F1A",
    "9A3E - 4B7C",
    "2D6F - 8E1B",
  ];

  return (
    <Card className="min-w-105">
      <CardHeader>
        <CardTitle>Códigos de respaldo</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <Alert className="max-w-md border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
          <AlertTriangleIcon />

          <AlertTitle>Your subscription will expire in 3 days.</AlertTitle>

          <AlertDescription>
            Renew now to avoid service interruption or upgrade to a paid plan to continue using the
            service.
          </AlertDescription>
        </Alert>

        <div className="flex flex-col gap-3 rounded-md border p-5">
          {backupCodes.map((code, index) => {
            return (
              <Fragment key={index}>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{code}</span>

                  <Copy size={16} className="text-xs text-muted-foreground" />
                </div>

                {index < backupCodes.length - 1 && <Separator />}
              </Fragment>
            );
          })}
        </div>

        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Códigos disponibles</span>

          <span className="font-medium">8 de 10</span>
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
