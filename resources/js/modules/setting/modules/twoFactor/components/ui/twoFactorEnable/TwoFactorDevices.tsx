import type { JSX } from "react";
import { Fragment, useEffect, useRef, useState } from "react";

import { Link, router, usePage } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";

import {
  AlertTriangleIcon,
  ChevronRight,
  EllipsisVertical,
  MonitorSmartphone,
  Plus,
} from "lucide-react";

import {
  TrustedDeviceAddDialog,
  TrustedDeviceAlreadyRegisteredDialog,
  TrustedDeviceDetailsDialog,
  TrustedDeviceExpiredDialog,
  TrustedDeviceForceDestroyDialog,
  TrustedDeviceReactivationDialog,
  TrustedDeviceRenameDialog,
  TrustedDeviceRenewTrustDialog,
  TrustedDeviceRevokeAllDialog,
  TrustedDeviceRevokedDialog,
  TrustedDeviceRevokeDialog,
} from "@/modules/setting/modules/trustedDevice/components/dialog";
import {
  type TrustedDeviceAddDeviceDialogKind,
  trustedDeviceDialogKind,
  trustedDeviceRowActionKey,
  trustedDeviceRowActions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import type { TrustedDevicePreview } from "@/modules/setting/modules/trustedDevice/types/trustedDevicePreview";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";
import { deviceLabel, getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { index as devicesIndex } from "@/shared/wayfinder/routes/setting/security/trusted-devices";

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
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

import type { TwoFactorDeviceSectionActionKey } from "../../../data/twoFactorEnable";
import { twoFactorDeviceSectionActionKey } from "../../../data/twoFactorEnable";

type TwoFactorDevicesPageProps = SharedData & {
  currentDevicePreview?: TrustedDevicePreview | null;
  currentDeviceMatch?: TrustedDevice | null;
  trustedDevices?: TrustedDevice[];
};

type TwoFactorDeviceActionKey =
  (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];

interface SectionDialogState extends DialogClosingState {
  kind: TwoFactorDeviceSectionActionKey | TrustedDeviceAddDeviceDialogKind;
}

function resolveAddDeviceKind(
  currentDeviceMatch: TrustedDevice | null,
): TrustedDeviceAddDeviceDialogKind {
  if (currentDeviceMatch === null) {
    return trustedDeviceDialogKind.addDevice;
  }

  if (currentDeviceMatch.deletedAt !== null) {
    return trustedDeviceDialogKind.deviceRevoked;
  }

  if (!currentDeviceMatch.isActive) {
    return trustedDeviceDialogKind.deviceExpired;
  }

  return trustedDeviceDialogKind.deviceAlreadyRegistered;
}

function TwoFactorDevices(): JSX.Element {
  const { currentDevicePreview, currentDeviceMatch, trustedDevices } =
    usePage<TwoFactorDevicesPageProps>().props;

  const hasLoadedTrustedDeviceData =
    trustedDevices !== undefined &&
    currentDevicePreview !== undefined &&
    currentDeviceMatch !== undefined;

  const [hasFreshTrustedDeviceData, setHasFreshTrustedDeviceData] = useState<boolean>(
    hasLoadedTrustedDeviceData,
  );
  const [hasTrustedDeviceDataLoadError, setHasTrustedDeviceDataLoadError] =
    useState<boolean>(false);

  const hasRequestedTrustedDeviceDataRef = useRef<boolean>(false);

  const isLoadingTrustedDeviceData = !hasFreshTrustedDeviceData || !hasLoadedTrustedDeviceData;
  const isShowingTrustedDeviceDataLoadError =
    hasTrustedDeviceDataLoadError && hasFreshTrustedDeviceData && !hasLoadedTrustedDeviceData;

  useEffect(() => {
    if (hasLoadedTrustedDeviceData || hasRequestedTrustedDeviceDataRef.current) {
      return;
    }

    hasRequestedTrustedDeviceDataRef.current = true;

    router.reload({
      only: ["trustedDevices", "currentDevicePreview", "currentDeviceMatch"],
      onError: () => {
        setHasTrustedDeviceDataLoadError(true);
      },
      onHttpException: () => {
        setHasTrustedDeviceDataLoadError(true);

        return false;
      },
      onNetworkError: () => {
        setHasTrustedDeviceDataLoadError(true);

        return false;
      },
      onCancel: () => {
        setHasTrustedDeviceDataLoadError(true);
      },
      onFinish: () => {
        setHasFreshTrustedDeviceData(true);
      },
    });
  }, [hasLoadedTrustedDeviceData, hasTrustedDeviceDataLoadError]);

  const handleRetryTrustedDeviceDataLoad = (): void => {
    hasRequestedTrustedDeviceDataRef.current = false;
    setHasFreshTrustedDeviceData(false);
    setHasTrustedDeviceDataLoadError(false);
  };

  const devices = trustedDevices ?? [];
  const hasActiveDevices = devices.some((device) => device.deletedAt === null && device.isActive);
  const hasRevocableDevices = devices.some((device) => device.deletedAt === null);

  const sectionDialog = useDialog<SectionDialogState | null>(null);

  const handleRevokeAllDevices = (): void => {
    sectionDialog.show({
      kind: twoFactorDeviceSectionActionKey.revokeAllDevices,
      closing: false,
    });
  };

  const handleAddDevice = (): void => {
    if (currentDeviceMatch === undefined) {
      return;
    }

    const kind = resolveAddDeviceKind(currentDeviceMatch);

    sectionDialog.show({ kind, closing: false });
  };

  const handleSectionDialogClose = createDialogCloseHandler(sectionDialog);

  const renderSectionDialog = (): JSX.Element | null => {
    if (sectionDialog.state === null) {
      return null;
    }

    const isClosing = sectionDialog.state.closing;

    switch (sectionDialog.state.kind) {
      case trustedDeviceDialogKind.addDevice:
        return (
          <TrustedDeviceAddDialog
            preview={currentDevicePreview ?? null}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case trustedDeviceDialogKind.deviceAlreadyRegistered:
        if (currentDeviceMatch === null || currentDeviceMatch === undefined) {
          return null;
        }

        return (
          <TrustedDeviceAlreadyRegisteredDialog
            existingDevice={currentDeviceMatch}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case trustedDeviceDialogKind.deviceRevoked:
        if (currentDeviceMatch === null || currentDeviceMatch === undefined) {
          return null;
        }

        return (
          <TrustedDeviceRevokedDialog
            existingDevice={currentDeviceMatch}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case trustedDeviceDialogKind.deviceExpired:
        if (currentDeviceMatch === null || currentDeviceMatch === undefined) {
          return null;
        }

        return (
          <TrustedDeviceExpiredDialog
            existingDevice={currentDeviceMatch}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case twoFactorDeviceSectionActionKey.revokeAllDevices:
        return (
          <TrustedDeviceRevokeAllDialog open={!isClosing} onClose={handleSectionDialogClose} />
        );

      default:
        return null;
    }
  };

  return (
    <>
      {isShowingTrustedDeviceDataLoadError ? (
        <TwoFactorDevicesLoadError onRetry={handleRetryTrustedDeviceDataLoad} />
      ) : isLoadingTrustedDeviceData ? (
        <TwoFactorDevicesSkeleton />
      ) : (
        <>
          <Alert className={alertVariants.info}>
            <AlertTriangleIcon />

            <AlertTitle className="line-clamp-4">
              Los dispositivos de confianza reducen la frecuencia con la que se te solicita el
              código de verificación.
            </AlertTitle>

            <AlertDescription>
              Revoca cualquier dispositivo que ya no utilices para mantener tu cuenta segura.
            </AlertDescription>
          </Alert>

          <div className="flex items-center justify-between gap-4 font-semibold">
            <span className="text-lg">Dispositivos registrados</span>

            <Button onClick={handleAddDevice} type="button" variant="outline">
              <Plus data-icon="inline-end" />
              Agregar dispositivo
            </Button>
          </div>

          <div className="flex flex-col gap-3 rounded-md border p-5">
            {!hasActiveDevices && (
              <div className="mb-1 text-sm text-muted-foreground">
                No tienes dispositivos de confianza activos.
              </div>
            )}

            {devices.length > 0 && <TwoFactorDevicesItems devices={devices} />}
          </div>

          <div className="flex flex-col gap-3">
            <Button asChild className="w-full py-6" variant="outline">
              <Link href={devicesIndex.url()}>
                Mostrar todos los dispositivos
                <ChevronRight />
              </Link>
            </Button>

            <Button
              type="button"
              variant="destructive"
              className="w-full py-6 dark:bg-red-700 dark:text-white dark:hover:bg-red-800"
              disabled={!hasRevocableDevices}
              onClick={handleRevokeAllDevices}
            >
              <MonitorSmartphone className="mr-2 h-4 w-4" />
              Revocar todos
            </Button>
          </div>

          <Alert className={alertVariants.info}>
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
      )}

      {renderSectionDialog()}
    </>
  );
}

interface TwoFactorDevicesLoadErrorProps {
  onRetry: () => void;
}

function TwoFactorDevicesLoadError({ onRetry }: TwoFactorDevicesLoadErrorProps): JSX.Element {
  return (
    <Alert className={cn(alertVariants.destructive, "my-2")}>
      <AlertTriangleIcon aria-hidden="true" />
      <AlertTitle>No se pudieron cargar los dispositivos de confianza</AlertTitle>

      <AlertDescription className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <span>Comprueba tu conexión e inténtalo de nuevo.</span>

        <Button onClick={onRetry} size="sm" type="button" variant="outline">
          Reintentar
        </Button>
      </AlertDescription>
    </Alert>
  );
}

function TwoFactorDevicesSkeleton(): JSX.Element {
  const deviceSkeletonKeys = ["first", "second", "third"];

  return (
    <div aria-busy="true" className="flex flex-col gap-6">
      <span className="sr-only" role="status">
        Cargando dispositivos de confianza.
      </span>

      <div className="flex flex-col gap-2 rounded-lg border p-4">
        <div className="flex items-center gap-2">
          <Skeleton aria-hidden="true" className="size-4 shrink-0 rounded-full" />
          <Skeleton aria-hidden="true" className="h-4 w-full max-w-md" />
        </div>
        <Skeleton aria-hidden="true" className="ml-6 h-4 w-4/5 max-w-lg" />
      </div>

      <div className="flex items-center justify-between gap-4">
        <Skeleton aria-hidden="true" className="h-6 w-40 max-w-full" />
        <Skeleton aria-hidden="true" className="h-10 w-44 max-w-full" />
      </div>

      <div className="flex flex-col gap-3 rounded-md border p-5">
        {deviceSkeletonKeys.map((deviceSkeletonKey) => (
          <div className="flex items-center justify-between gap-4" key={deviceSkeletonKey}>
            <div className="flex min-w-0 flex-1 items-start gap-2.5">
              <Skeleton aria-hidden="true" className="size-8 shrink-0 rounded-md" />
              <div className="flex min-w-0 flex-1 flex-col gap-2">
                <Skeleton aria-hidden="true" className="h-4 w-36 max-w-full" />
                <Skeleton aria-hidden="true" className="h-3 w-48 max-w-full" />
                <Skeleton aria-hidden="true" className="h-3 w-32 max-w-full" />
              </div>
            </div>
            <div className="flex items-center gap-4">
              <Skeleton aria-hidden="true" className="size-3 shrink-0 rounded-full" />
              <Skeleton aria-hidden="true" className="size-9 shrink-0 rounded-md" />
            </div>
          </div>
        ))}
      </div>

      <div className="flex flex-col gap-3">
        <Skeleton aria-hidden="true" className="h-12 w-full" />
        <Skeleton aria-hidden="true" className="h-12 w-full" />
      </div>

      <div className="flex flex-col gap-3 rounded-lg border p-4">
        <Skeleton aria-hidden="true" className="h-4 w-20" />
        <Skeleton aria-hidden="true" className="h-4 w-full max-w-sm" />
        <Skeleton aria-hidden="true" className="h-4 w-4/5 max-w-sm" />
        <Skeleton aria-hidden="true" className="h-4 w-3/5 max-w-sm" />
      </div>
    </div>
  );
}

interface TwoFactorDevicesItemsProps {
  devices: TrustedDevice[];
}

interface DialogActionState extends DialogClosingState {
  kind: TwoFactorDeviceActionKey;
  device: TrustedDevice;
}

function TwoFactorDevicesItems({ devices }: TwoFactorDevicesItemsProps) {
  const dialogDevice = useDialog<DialogActionState | null>(null);

  const handleDeviceAction = (action: TwoFactorDeviceActionKey, device: TrustedDevice) => {
    dialogDevice.show({ kind: action, device, closing: false });
  };

  const handleDialogClose = createDialogCloseHandler(dialogDevice);

  const renderDialogDevice = (): JSX.Element | null => {
    if (dialogDevice.state === null) {
      return null;
    }

    const isClosing = dialogDevice.state.closing;

    const selectedDevice =
      devices.find((device) => device.id === dialogDevice.state?.device.id) ?? null;

    if (selectedDevice === null) {
      return null;
    }

    switch (dialogDevice.state.kind) {
      case trustedDeviceRowActionKey.viewDevice:
        return (
          <TrustedDeviceDetailsDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case trustedDeviceRowActionKey.renameDevice:
        return (
          <TrustedDeviceRenameDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case trustedDeviceRowActionKey.renewTrust:
        return (
          <TrustedDeviceRenewTrustDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case trustedDeviceRowActionKey.revokeDevice:
        return (
          <TrustedDeviceRevokeDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case trustedDeviceRowActionKey.reactivate:
        return (
          <TrustedDeviceReactivationDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case trustedDeviceRowActionKey.forceDestroy:
        return (
          <TrustedDeviceForceDestroyDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      default:
        return null;
    }
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
                <span className="max-w-40 truncate text-sm font-medium" title={deviceLabel(device)}>
                  {deviceLabel(device)}
                </span>

                {device.browser !== null && device.browser !== "" && (
                  <span className="truncate text-xs text-muted-foreground">
                    {device.osName} - {device.browser}
                    {device.browserVersion !== null && device.browserVersion !== "" && (
                      <> {device.browserVersion}</>
                    )}
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
        {trustedDeviceRowActions.map((group, groupIndex) => (
          <Fragment key={groupIndex}>
            <DropdownMenuGroup>
              {group.label && <DropdownMenuLabel>{group.label}</DropdownMenuLabel>}

              {group.actions.map((action) => {
                const Icon = action.icon;
                const isActionEnabled = action.isEnabled(device);

                return (
                  <DropdownMenuItem
                    key={action.key}
                    disabled={!isActionEnabled}
                    onClick={(event) => {
                      if (!isActionEnabled) {
                        event.preventDefault();
                        return;
                      }

                      handleClick(action.key);
                    }}
                    className={cn(
                      action.className,
                      !isActionEnabled && "cursor-not-allowed opacity-50",
                    )}
                  >
                    <Icon
                      className={cn(
                        action.iconClassName ?? "text-muted-foreground",
                        !isActionEnabled && "opacity-70",
                      )}
                    />
                    {action.label}
                  </DropdownMenuItem>
                );
              })}
            </DropdownMenuGroup>

            {groupIndex < trustedDeviceRowActions.length - 1 && <DropdownMenuSeparator />}
          </Fragment>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default TwoFactorDevices;
