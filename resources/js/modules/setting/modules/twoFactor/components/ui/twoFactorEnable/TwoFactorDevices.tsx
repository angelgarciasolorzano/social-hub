import type { JSX } from "react";
import { Fragment } from "react";

import { Form } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";
import { ImWindows } from "react-icons/im";
import { MdOutlineLaptopMac, MdPhoneAndroid } from "react-icons/md";

import dayjs from "dayjs";
import {
  AlertTriangleIcon,
  EllipsisVertical,
  MonitorSmartphone,
  Plus,
  Smartphone,
} from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/twoFactor/types/trustedDevice";

import { destroyAll as destroyAllRoute } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { useDialog } from "@/shared/hooks";

import type { TwoFactorDeviceActionKey } from "../../../data/twoFactorEnable";
import { twoFactorDeviceActionKey, twoFactorDeviceActions } from "../../../data/twoFactorEnable";
import DeviceDetailsDialog from "../../dialog/trustedDevice/DeviceDetailsDialog";

interface TwoFactorDevicesProps {
  devices: TrustedDevice[];
}

function TwoFactorDevices({ devices }: TwoFactorDevicesProps): JSX.Element {
  const hasDevices = devices.length > 0;

  const deviceLabel = (device: TrustedDevice): string => {
    return device.name ?? device.userAgent ?? "Dispositivo desconocido";
  };

  const fromNow = (iso: string | null): string => {
    if (iso === null) {
      return "nunca";
    }

    return dayjs(iso).fromNow();
  };

  const getDeviceIcon = (device: TrustedDevice, className: string): JSX.Element => {
    const os = device.osName?.toLowerCase() ?? "";
    const isMobile = device.isMobile;

    if (isMobile) {
      if (os.includes("apple") || os.includes("mac")) {
        return <Smartphone className={className} />;
      }

      return <MdPhoneAndroid className={className} />;
    }

    if (os.includes("mac")) return <MdOutlineLaptopMac className={className} />;

    return <ImWindows className={className} />;
  };

  return (
    <>
      <Alert className="border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300">
        <AlertTriangleIcon />

        <AlertTitle className="line-clamp-4">
          Los dispositivos de confianza reducen la frecuencia con la que se te solicita el código de
          verificación.
        </AlertTitle>

        <AlertDescription>
          Revoca cualquier dispositivo que ya no utilices para mantener tu cuenta segura.
        </AlertDescription>
      </Alert>

      <div className="flex items-center justify-between gap-4 font-semibold">
        <span className="text-sm">Dispositivos registrados</span>

        <Button variant="outline">
          <Plus data-icon="inline-end" />
          Agregar dispositivo
        </Button>
      </div>

      <div className="flex flex-col gap-3 rounded-md border p-5">
        {hasDevices ? (
          <TwoFactorDevicesItems
            devices={devices}
            deviceLabel={deviceLabel}
            fromNow={fromNow}
            getDeviceIcon={getDeviceIcon}
          />
        ) : (
          <div className="text-sm text-muted-foreground">
            No tienes dispositivos de confianza configurados.
          </div>
        )}
      </div>

      <Form {...destroyAllRoute.delete()}>
        {() => (
          <Button
            type="submit"
            variant="destructive"
            className="w-full py-6 dark:bg-red-700 dark:text-white dark:hover:bg-red-800"
            disabled={!hasDevices}
          >
            <MonitorSmartphone className="mr-2 h-4 w-4" />
            Revocar todos
          </Button>
        )}
      </Form>

      <Alert className="border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300">
        <AlertTitle>Consejos</AlertTitle>

        <AlertDescription>
          <ul className="list-inside list-disc space-y-2">
            <li>Usa dispositivos que sean solo tuyos.</li>
            <li>Cierra sesión en dispositivos que ya no uses.</li>
            <li>Los cambios pueden tardar unos minutos en reflejarse.</li>
          </ul>
        </AlertDescription>
      </Alert>
    </>
  );
}

type TwoFactorDevicesItemsProps = Pick<TwoFactorDevicesProps, "devices"> & {
  deviceLabel: (device: TrustedDevice) => string;
  fromNow: (iso: string | null) => string;
  getDeviceIcon: (device: TrustedDevice, className: string) => JSX.Element;
};

interface DialogActionState {
  kind: TwoFactorDeviceActionKey;
  device: TrustedDevice;
  closing: boolean;
}

const DIALOG_EXIT_ANIMATION_MS = 200;

function TwoFactorDevicesItems({
  devices,
  deviceLabel,
  fromNow,
  getDeviceIcon,
}: TwoFactorDevicesItemsProps) {
  const dialogDevice = useDialog<DialogActionState | null>(null);

  const handleDeviceAction = (action: TwoFactorDeviceActionKey, device: TrustedDevice) => {
    dialogDevice.show({ kind: action, device, closing: false });
  };

  const handleDialogClose = (): void => {
    const current = dialogDevice.state;

    if (current === null || current.closing) {
      return;
    }

    dialogDevice.setState({ ...current, closing: true });

    setTimeout(() => {
      dialogDevice.hide();
    }, DIALOG_EXIT_ANIMATION_MS);
  };

  const renderDialogDevice = (): JSX.Element | null | undefined => {
    if (dialogDevice.state === null) {
      return null;
    }

    switch (dialogDevice.state.kind) {
      case twoFactorDeviceActionKey.viewDevice:
        return (
          <DeviceDetailsDialog
            device={dialogDevice.state.device}
            open={!dialogDevice.state.closing}
            onClose={handleDialogClose}
          />
        );

      case twoFactorDeviceActionKey.renameDevice:
        //return <RenameDeviceDialog device={dialogDevice.state.device} ... />;
        break;

      case twoFactorDeviceActionKey.renewTrust:
        //return <RenewTrustDeviceDialog device={dialogDevice.state.device} ... />;
        break;

      case twoFactorDeviceActionKey.revokeDevice:
        //return <RevokeDeviceDialog device={dialogDevice.state.device} ... />;
        break;

      default:
        return null;
    }

    return null;
  };

  return (
    <>
      {devices.map((device, index) => (
        <Fragment key={device.id}>
          <div className="flex items-center justify-between gap-4">
            <div className="flex min-w-0 flex-1 items-start gap-2.5">
              <div className="flex h-8 w-8 rounded-md bg-muted p-1 dark:bg-primary-foreground">
                {getDeviceIcon(device, "h-6 w-6 text-gray-600 dark:text-gray-400")}
              </div>

              <div className="flex flex-col gap-0.5">
                <span className="truncate text-sm font-medium" title={deviceLabel(device)}>
                  {deviceLabel(device)}
                </span>

                {device.browser !== null && device.browser !== "" && (
                  <span className="truncate text-xs text-muted-foreground">
                    {device.osName} - {device.browser}
                  </span>
                )}

                <span className="text-xs text-muted-foreground">
                  Último uso: {fromNow(device.lastUsedAt)}
                </span>
              </div>
            </div>

            <div className="flex items-center gap-4">
              <FaCircle className="h-3 w-3 text-red-600 dark:text-red-500" />

              <DeviceActionsDropdown device={device} onActionClick={handleDeviceAction} />
            </div>
          </div>

          {index < devices.length - 1 && <Separator />}
        </Fragment>
      ))}

      {renderDialogDevice()}
    </>
  );
}

interface DeviceActionsDropdownProps {
  device: TrustedDevice;
  onActionClick: (action: TwoFactorDeviceActionKey, device: TrustedDevice) => void;
}

function DeviceActionsDropdown({ device, onActionClick }: DeviceActionsDropdownProps): JSX.Element {
  const handleClick = (action: TwoFactorDeviceActionKey): void => {
    onActionClick(action, device);
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="icon">
          <EllipsisVertical />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="w-60" align="end">
        {twoFactorDeviceActions.map((group, groupIndex) => (
          <Fragment key={groupIndex}>
            <DropdownMenuGroup>
              {group.label && <DropdownMenuLabel>{group.label}</DropdownMenuLabel>}

              {group.actions.map((action) => {
                const Icon = action.icon;

                return (
                  <DropdownMenuItem
                    key={action.key}
                    onClick={() => {
                      handleClick(action.key);
                    }}
                    className={action.className}
                  >
                    <Icon className={action.iconClassName} />
                    {action.label}
                  </DropdownMenuItem>
                );
              })}
            </DropdownMenuGroup>

            {groupIndex < twoFactorDeviceActions.length - 1 && <DropdownMenuSeparator />}
          </Fragment>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default TwoFactorDevices;
