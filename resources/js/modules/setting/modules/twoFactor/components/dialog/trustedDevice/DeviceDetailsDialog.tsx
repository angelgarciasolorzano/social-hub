import type { JSX } from "react";

import dayjs from "dayjs";
import "dayjs/locale/es";
import relativeTime from "dayjs/plugin/relativeTime";
import {
  CalendarClock,
  CalendarRange,
  CircleCheck,
  Clock,
  Eye,
  Globe,
  MapPin,
  MonitorSmartphone,
  Trash2,
} from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/shadcn/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import {
  Item,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from "@/shared/components/shadcn/ui/item";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import type { TrustedDevice } from "../../../types/trustedDevice";

dayjs.extend(relativeTime);
dayjs.locale("es");

function formatLongDate(iso: string | null): string {
  if (iso === null) {
    return "Nunca";
  }
  return dayjs(iso).format("D [de] MMMM [del] YYYY, h:mm A");
}

function formatTimeUntil(iso: string | null): string {
  if (iso === null || dayjs(iso).isBefore(dayjs())) {
    return "vencido";
  }

  return dayjs(iso).fromNow();
}

interface DeviceDetailsDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function DeviceDetailsDialog({ device, open, onClose }: DeviceDetailsDialogProps): JSX.Element {
  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) onClose();
      }}
    >
      <DialogContent className="max-w-4xl min-w-3xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Eye className="h-5 w-5 text-muted-foreground" />
              Detalles del dispositivo
              <Badge className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
                Activo
              </Badge>
            </div>
          </DialogTitle>
          <DialogDescription>
            Consulta la información completa de este dispositivo de confianza.
          </DialogDescription>
        </DialogHeader>

        <div>
          <Card>
            <CardHeader>
              <CardTitle>Informacion del dispositivo</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-[1fr_auto_1fr] gap-6">
                <div>
                  <Item>
                    <ItemMedia>
                      <Globe />
                    </ItemMedia>
                    <ItemContent>
                      <ItemTitle>Navegador</ItemTitle>
                      <ItemDescription>{device.browser}</ItemDescription>
                    </ItemContent>
                  </Item>

                  <Item>
                    <ItemMedia>
                      <MonitorSmartphone />
                    </ItemMedia>
                    <ItemContent>
                      <ItemTitle>Sistema operativo</ItemTitle>
                      <ItemDescription>{device.osName}</ItemDescription>
                    </ItemContent>
                  </Item>

                  <Item>
                    <ItemMedia>
                      <MapPin />
                    </ItemMedia>
                    <ItemContent>
                      <ItemTitle>Dirección IP</ItemTitle>
                      <ItemDescription>{device.ip}</ItemDescription>
                    </ItemContent>
                  </Item>

                  <Item>
                    <ItemMedia>
                      <CalendarRange />
                    </ItemMedia>
                    <ItemContent>
                      <ItemTitle>Fecha de registro</ItemTitle>
                      <ItemDescription>{formatLongDate(device.createdAt)}</ItemDescription>
                    </ItemContent>
                  </Item>
                </div>

                <Separator orientation="vertical" />

                <div>
                  <Item>
                    <ItemMedia>
                      <Clock />
                    </ItemMedia>
                    <ItemContent>
                      <ItemTitle>Último acceso</ItemTitle>
                      <ItemDescription>
                        <span className="mb-0.5 block text-sm text-muted-foreground">
                          {dayjs(device.lastUsedAt).fromNow()}
                        </span>

                        <span className="text-sm">{formatLongDate(device.lastUsedAt)}</span>
                      </ItemDescription>
                    </ItemContent>
                  </Item>

                  <Item>
                    <ItemMedia>
                      <CalendarClock />
                    </ItemMedia>
                    <ItemContent>
                      <ItemTitle>Fecha de expiración</ItemTitle>
                      <ItemDescription>
                        <span className="text-sm">{formatLongDate(device.expiresAt)}</span>

                        <span className="mb-0.5 block text-sm text-muted-foreground">
                          ({formatTimeUntil(device.expiresAt)})
                        </span>
                      </ItemDescription>
                    </ItemContent>
                  </Item>

                  <Separator className="mb-3" />

                  <div className="flex flex-col gap-3">
                    <span className="font-semibold dark:text-white">Estado</span>

                    <Alert className="border-green-200 bg-green-50 text-green-900 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-500">
                      <CircleCheck />

                      <AlertTitle>Este dispositivo está activado</AlertTitle>
                      <AlertDescription>
                        No se te pedirá el código de verificación cada vez que inices sesión desde
                        este dispositivo hasta su expiración.
                      </AlertDescription>
                    </Alert>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <DialogFooter>
          <Button type="button" className="cursor-pointer">
            Cerrar
          </Button>

          <Button
            variant="destructive"
            className="dark:bg-red-700 dark:text-white dark:hover:bg-red-800"
          >
            <Trash2 />
            Revokar dispositivo
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default DeviceDetailsDialog;
